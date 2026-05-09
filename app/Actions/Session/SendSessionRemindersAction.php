<?php

namespace App\Actions\Session;

use App\Enums\RegistrationStatus;
use App\Models\Registration;
use App\Notifications\SessionReminderNotification;
use App\Settings\BookingSettings;

class SendSessionRemindersAction
{
    public function __construct(private readonly BookingSettings $settings) {}

    public function execute(): int
    {
        $hours       = $this->settings->session_reminder_hours_before;
        $windowStart = now()->addHours($hours - 1);
        $windowEnd   = now()->addHours($hours);

        $registrations = Registration::where('status', RegistrationStatus::Registered->value)
            ->whereNull('reminded_at')
            ->whereHas('conversationTable', fn ($q) =>
                $q->whereBetween('scheduled_at', [$windowStart, $windowEnd])
            )
            ->with(['user', 'conversationTable.level'])
            ->get();

        foreach ($registrations as $registration) {
            $registration->user->notify(new SessionReminderNotification($registration));
            $registration->update(['reminded_at' => now()]);
        }

        return $registrations->count();
    }
}
