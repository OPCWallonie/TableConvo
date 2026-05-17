<?php

namespace App\Actions\GlobalWaitlist;

use App\Enums\GlobalWaitlistEntryStatus;
use App\Enums\GlobalWaitlistSource;
use App\Enums\RegistrationStatus;
use App\Models\GlobalWaitlistEntry;
use App\Models\Registration;
use App\Models\User;
use App\Notifications\MovedToGlobalPoolNotification;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class MoveToGlobalWaitlistAction
{
    /**
     * Annule une inscription (Registered ou Waitlist) et crée une entrée au vivier global.
     *
     * @param  Registration         $registration  Inscription à basculer vers le vivier.
     * @param  User                 $admin         Admin déclencheur de l'action.
     * @param  GlobalWaitlistSource $source        Origine de l'entrée vivier.
     * @param  string|null          $adminReason   Raison admin (obligatoire si source = AdminCancelledRegistration).
     * @param  bool                 $recreditCard  Si true et registration Registered, recrédite la séance.
     * @return GlobalWaitlistEntry  Entrée vivier créée avec statut Pending.
     * @throws RuntimeException cannot_move_to_pool — statut registration non valide.
     * @throws RuntimeException admin_reason_required — raison manquante pour annulation confirmée.
     * @throws RuntimeException user_level_missing — utilisateur sans niveau attribué.
     */
    public function execute(
        Registration $registration,
        User $admin,
        GlobalWaitlistSource $source,
        ?string $adminReason = null,
        bool $recreditCard = true,
    ): GlobalWaitlistEntry {
        if (! in_array($registration->status, [RegistrationStatus::Registered, RegistrationStatus::Waitlist], true)) {
            throw new RuntimeException('cannot_move_to_pool');
        }

        if ($source === GlobalWaitlistSource::AdminCancelledRegistration && blank($adminReason)) {
            throw new RuntimeException('admin_reason_required');
        }

        $user = $registration->user;

        if ($user->level_id === null) {
            throw new RuntimeException('user_level_missing');
        }

        return DB::transaction(function () use ($registration, $admin, $source, $adminReason, $recreditCard, $user) {
            $wasRegistered = $registration->status === RegistrationStatus::Registered;
            $oldPosition   = $registration->waitlist_position;
            $sourceTable   = $registration->conversationTable;

            // Recréditation conditionnelle sur inscription confirmée
            if ($wasRegistered && $recreditCard && $registration->card_id) {
                $card = $registration->card;
                if ($card->isActive()) {
                    $card->increment('sessions_remaining');
                } else {
                    activity()
                        ->performedOn($registration)
                        ->causedBy($admin)
                        ->withProperties(['reason' => 'card_inactive_or_expired'])
                        ->log('Recréditation impossible : carte inactive ou expirée');
                }
            }

            $registration->update([
                'status'       => RegistrationStatus::Cancelled,
                'cancelled_at' => now(),
                'cancelled_by' => $admin->id,
            ]);

            // Décalage FIFO si on retire un waitlister de la session source
            if (! $wasRegistered && $oldPosition !== null) {
                $sourceTable->registrations()
                    ->where('status', RegistrationStatus::Waitlist->value)
                    ->where('waitlist_position', '>', $oldPosition)
                    ->decrement('waitlist_position');
            }

            $entry = GlobalWaitlistEntry::create([
                'user_id'                => $user->id,
                'level_id'               => $user->level_id,
                'requested_at'           => now(),
                'source'                 => $source,
                'source_registration_id' => $registration->id,
                'admin_reason'           => $adminReason,
                'created_by'             => $admin->id,
                'status'                 => GlobalWaitlistEntryStatus::Pending,
            ]);

            activity()
                ->performedOn($entry)
                ->causedBy($admin)
                ->log('Entrée vivier global');

            DB::afterCommit(function () use ($user, $entry) {
                $user->notify(new MovedToGlobalPoolNotification($entry->fresh()));
            });

            return $entry;
        });
    }
}
