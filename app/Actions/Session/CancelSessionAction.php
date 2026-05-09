<?php

namespace App\Actions\Session;

use App\Actions\Card\ExtendCardValidityAction;
use App\Enums\RegistrationStatus;
use App\Enums\SessionStatus;
use App\Models\ConversationTable;
use App\Models\User;
use App\Notifications\SessionCancelledNotification;
use App\Settings\BookingSettings;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CancelSessionAction
{
    public function __construct(
        private readonly BookingSettings $settings,
        private readonly ExtendCardValidityAction $extendCard,
    ) {}

    public function execute(ConversationTable $table, User $admin, string $reason): ConversationTable
    {
        if ($table->status !== SessionStatus::Scheduled) {
            throw new RuntimeException('session_not_cancellable');
        }

        if ($table->scheduled_at->isPast()) {
            throw new RuntimeException('session_already_passed');
        }

        return DB::transaction(function () use ($table, $admin, $reason) {
            $registrations = $table->registrations()
                ->whereIn('status', [
                    RegistrationStatus::Registered->value,
                    RegistrationStatus::Waitlist->value,
                ])
                ->with(['card', 'user'])
                ->get();

            $notifications = [];
            $creditCount   = 0;

            foreach ($registrations as $registration) {
                $originalStatus = $registration->status;

                $registration->update([
                    'status'       => RegistrationStatus::Cancelled,
                    'cancelled_at' => now(),
                    'cancelled_by' => $admin->id,
                ]);

                if ($originalStatus === RegistrationStatus::Waitlist) {
                    $notifications[] = [
                        'user'             => $registration->user,
                        'registration'     => $registration,
                        'compensationType' => 'waitlist_notice',
                    ];
                    continue;
                }

                $card = $registration->card;

                if ($card && $card->isActive()) {
                    $card->increment('sessions_remaining');
                    $creditCount++;

                    $thresholdDays = $this->settings->post_cancellation_extension_threshold_days;
                    $extensionDays = $this->settings->post_cancellation_card_extension_days;

                    if (now()->diffInDays($card->expires_at) <= $thresholdDays) {
                        $this->extendCard->execute($card, $extensionDays, $admin);
                        $compensationType = 'recredit_and_extend';
                    } else {
                        $compensationType = 'recredit_only';
                    }
                } else {
                    activity()
                        ->performedOn($registration)
                        ->causedBy($admin)
                        ->withProperties(['reason' => 'card_not_active'])
                        ->log('Séance non recréditée — carte inactive ou expirée');

                    $compensationType = 'expired_no_compensation';
                }

                $notifications[] = [
                    'user'             => $registration->user,
                    'registration'     => $registration,
                    'compensationType' => $compensationType,
                ];
            }

            $table->update([
                'status'              => SessionStatus::Cancelled,
                'cancelled_at'        => now(),
                'cancellation_reason' => $reason,
            ]);

            activity()
                ->performedOn($table)
                ->causedBy($admin)
                ->withProperties([
                    'reason'                 => $reason,
                    'registrations_credited' => $creditCount,
                ])
                ->log("Session annulée par l'admin — crédits restitués");

            DB::afterCommit(function () use ($table, $notifications, $reason) {
                foreach ($notifications as $n) {
                    $n['user']->notify(
                        new SessionCancelledNotification(
                            table:            $table,
                            registration:     $n['registration']->fresh(),
                            compensationType: $n['compensationType'],
                            reason:           $reason,
                        )
                    );
                }
            });

            return $table->fresh();
        });
    }
}
