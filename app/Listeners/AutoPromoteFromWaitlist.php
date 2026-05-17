<?php

namespace App\Listeners;

use App\Actions\Registration\PromoteFromWaitlistAction;
use App\Enums\RegistrationStatus;
use App\Events\RegistrationCancelled;
use App\Models\User;
use App\Settings\BookingSettings;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class AutoPromoteFromWaitlist
{
    public function __construct(
        private readonly BookingSettings $settings,
        private readonly PromoteFromWaitlistAction $promote,
    ) {}

    public function handle(RegistrationCancelled $event): void
    {
        if (! $this->settings->waitlist_auto_promote) {
            return;
        }

        $table = $event->registration->conversationTable;

        $nextInLine = $table->registrations()
            ->where('status', RegistrationStatus::Waitlist->value)
            ->orderBy('waitlist_position')
            ->first();

        if (! $nextInLine) {
            return;
        }

        try {
            $causer = $event->registration->cancelledBy
                ?? User::role('admin')->first();

            $this->promote->execute($nextInLine, $causer);
        } catch (RuntimeException $e) {
            Log::warning('AutoPromote skipped', [
                'registration_id' => $nextInLine->id,
                'reason'          => $e->getMessage(),
            ]);
        }
    }
}
