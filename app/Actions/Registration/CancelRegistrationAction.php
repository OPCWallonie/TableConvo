<?php

namespace App\Actions\Registration;

use App\Enums\CardStatus;
use App\Enums\RegistrationStatus;
use App\Enums\SessionStatus;
use App\Models\Registration;
use App\Models\User;
use App\Services\BusinessDay\BusinessDayService;
use App\Settings\BookingSettings;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CancelRegistrationAction
{
    public function __construct(
        private readonly BookingSettings $settings,
        private readonly BusinessDayService $businessDays,
    ) {}

    public function execute(Registration $registration, User $cancelledBy): Registration
    {
        // 1. La registration doit être en cours (Registered ou Waitlist)
        if (! in_array($registration->status, [RegistrationStatus::Registered, RegistrationStatus::Waitlist], true)) {
            throw new RuntimeException('cannot_cancel');
        }

        // 2. La session doit être planifiée et dans le futur
        $table = $registration->conversationTable;
        if ($table->status !== SessionStatus::Scheduled || $table->scheduled_at->isPast()) {
            throw new RuntimeException('session_unavailable');
        }

        // 3. Calcul de la deadline : J - N jours ouvrables, jusqu'à 23:59:59
        $deadline = $this->businessDays->subBusinessDays(
            $table->scheduled_at,
            $this->settings->cancellation_deadline_business_days
        )->endOfDay();

        // 4. Vérification de la deadline — admin peut forcer
        if (now()->gt($deadline) && ! $cancelledBy->hasRole('admin')) {
            throw new RuntimeException('deadline_passed');
        }

        return DB::transaction(function () use ($registration, $cancelledBy) {
            $registration->update([
                'status'       => RegistrationStatus::Cancelled,
                'cancelled_at' => now(),
                'cancelled_by' => $cancelledBy->id,
            ]);

            // 5b. Recréditation conditionnelle (uniquement si carte valide)
            if ($registration->card_id) {
                $card = $registration->card;
                if ($card->isActive()) {
                    $card->increment('sessions_remaining');
                } else {
                    activity()
                        ->performedOn($registration)
                        ->causedBy($cancelledBy)
                        ->withProperties(['reason' => 'card_inactive_or_expired'])
                        ->log('Recréditation impossible : carte inactive ou expirée');
                }
            }

            // 5c. Journal d'audit
            $isAdmin = $cancelledBy->hasRole('admin');
            activity()
                ->performedOn($registration)
                ->causedBy($cancelledBy)
                ->log($isAdmin ? 'Annulation par l\'admin' : 'Inscription annulée');

            return $registration->fresh();
        });
    }
}
