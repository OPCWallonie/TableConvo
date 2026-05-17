<?php

namespace App\Actions\GlobalWaitlist;

use App\Enums\GlobalWaitlistEntryStatus;
use App\Enums\RegistrationStatus;
use App\Enums\SessionStatus;
use App\Models\ConversationTable;
use App\Models\GlobalWaitlistEntry;
use App\Models\Registration;
use App\Models\User;
use App\Notifications\ReassignedFromGlobalPoolNotification;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ReassignFromGlobalWaitlistAction
{
    /**
     * Réassigne une entrée vivier vers une session compatible, crée la Registration résultante.
     *
     * Le statut de la Registration créée dépend de deux conditions :
     * - Si la table est pleine → Waitlist (indépendamment de la carte).
     * - Si place disponible + carte active → Registered (séance débitée).
     * - Si place disponible + pas de carte active → Waitlist.
     *
     * @param  GlobalWaitlistEntry $entry       Entrée vivier à réassigner (doit être Pending).
     * @param  ConversationTable   $targetTable Session cible (doit être Scheduled et future).
     * @param  User                $admin       Admin déclencheur de l'action.
     * @return Registration        Registration créée.
     * @throws RuntimeException entry_not_pending — entrée déjà traitée.
     * @throws RuntimeException target_table_not_scheduled — session non planifiée.
     * @throws RuntimeException target_table_in_past — session déjà passée.
     * @throws RuntimeException level_mismatch — niveau de la session différent de l'entrée.
     * @throws RuntimeException already_registered_on_target — utilisateur déjà inscrit sur cette session.
     */
    public function execute(
        GlobalWaitlistEntry $entry,
        ConversationTable $targetTable,
        User $admin,
    ): Registration {
        if ($entry->status !== GlobalWaitlistEntryStatus::Pending) {
            throw new RuntimeException('entry_not_pending');
        }

        if ($targetTable->status !== SessionStatus::Scheduled) {
            throw new RuntimeException('target_table_not_scheduled');
        }

        if ($targetTable->scheduled_at->isPast()) {
            throw new RuntimeException('target_table_in_past');
        }

        if ($targetTable->level_id !== $entry->level_id) {
            throw new RuntimeException('level_mismatch');
        }

        $alreadyRegistered = $targetTable->registrations()
            ->where('user_id', $entry->user_id)
            ->whereIn('status', [RegistrationStatus::Registered->value, RegistrationStatus::Waitlist->value])
            ->exists();

        if ($alreadyRegistered) {
            throw new RuntimeException('already_registered_on_target');
        }

        return DB::transaction(function () use ($entry, $targetTable, $admin) {
            $isFull   = $targetTable->isFull();
            $card     = null;
            $cardId   = null;
            $finalStatus = RegistrationStatus::Waitlist;

            if (! $isFull) {
                $card = $entry->user->activeCards()->where('sessions_remaining', '>', 0)->first();
                if ($card) {
                    $finalStatus = RegistrationStatus::Registered;
                    $cardId      = $card->id;
                    $card->decrement('sessions_remaining');
                }
            }

            $waitlistPosition = null;
            if ($finalStatus === RegistrationStatus::Waitlist) {
                $waitlistPosition = ($targetTable->registrations()
                    ->where('status', RegistrationStatus::Waitlist->value)
                    ->max('waitlist_position') ?? 0) + 1;
            }

            $newRegistration = Registration::create([
                'user_id'                => $entry->user_id,
                'conversation_table_id'  => $targetTable->id,
                'card_id'                => $cardId,
                'status'                 => $finalStatus,
                'waitlist_position'      => $waitlistPosition,
                'registered_at'          => now(),
            ]);

            $entry->update([
                'status'                       => GlobalWaitlistEntryStatus::Reassigned,
                'reassigned_to_registration_id' => $newRegistration->id,
            ]);

            activity()
                ->performedOn($entry)
                ->causedBy($admin)
                ->withProperties([
                    'target_table'  => $targetTable->id,
                    'final_status'  => $finalStatus->value,
                ])
                ->log('Réassignation depuis vivier');

            DB::afterCommit(function () use ($entry, $newRegistration) {
                $entry->user->notify(
                    new ReassignedFromGlobalPoolNotification($entry->fresh(), $newRegistration->fresh())
                );
            });

            return $newRegistration->fresh();
        });
    }
}
