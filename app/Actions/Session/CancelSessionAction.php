<?php

namespace App\Actions\Session;

use App\Enums\RegistrationStatus;
use App\Enums\SessionStatus;
use App\Models\ConversationTable;
use App\Models\User;
use App\Settings\BookingSettings;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CancelSessionAction
{
    public function __construct(private readonly BookingSettings $settings) {}

    public function execute(ConversationTable $table, User $admin, string $reason): ConversationTable
    {
        return DB::transaction(function () use ($table, $admin, $reason) {
            $table->update([
                'status' => SessionStatus::Cancelled,
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
            ]);

            $confirmedRegistrations = $table->registrations()
                ->where('status', RegistrationStatus::Registered->value)
                ->with('card')
                ->get();

            foreach ($confirmedRegistrations as $registration) {
                $registration->update([
                    'status' => RegistrationStatus::Cancelled,
                    'cancelled_at' => now(),
                    'cancelled_by' => $admin->id,
                ]);

                if ($registration->card_id && $registration->card) {
                    $card = $registration->card;
                    $card->increment('sessions_remaining');

                    $thresholdDays = $this->settings->post_cancellation_extension_threshold_days;
                    $extensionDays = $this->settings->post_cancellation_card_extension_days;

                    if ($card->expires_at->diffInDays(now()) <= $thresholdDays) {
                        $card->update(['expires_at' => Carbon::parse($card->expires_at)->addDays($extensionDays)]);
                    }
                }
            }

            activity()
                ->performedOn($table)
                ->causedBy($admin)
                ->withProperties(['reason' => $reason, 'registrations_credited' => $confirmedRegistrations->count()])
                ->log('Session annulée par l\'admin — crédits restitués');

            return $table->fresh();
        });
    }
}
