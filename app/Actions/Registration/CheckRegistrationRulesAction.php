<?php

namespace App\Actions\Registration;

use App\Enums\RegistrationStatus;
use App\Models\Card;
use App\Models\ConversationTable;
use App\Models\User;
use App\Settings\BookingSettings;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CheckRegistrationRulesAction
{
    public function __construct(private readonly BookingSettings $settings) {}

    /**
     * @return array{allowed: bool, reason: string|null}
     */
    public function execute(User $user, ConversationTable $table, bool $forWaitlist = false): array
    {
        if (! $user->hasLevel()) {
            return ['allowed' => false, 'reason' => 'no_level'];
        }

        if ($user->level_id !== $table->level_id) {
            return ['allowed' => false, 'reason' => 'wrong_level'];
        }

        $deadline = Carbon::parse($table->scheduled_at)->subHours($this->settings->registration_deadline_hours);
        if (now()->gt($deadline)) {
            return ['allowed' => false, 'reason' => 'deadline_passed'];
        }

        if (! $forWaitlist && $table->isFull()) {
            return ['allowed' => false, 'reason' => 'table_full'];
        }

        $weekStart = now()->startOfWeek();
        $weekEnd = now()->endOfWeek();
        $registrationsThisWeek = $user->registrations()
            ->whereIn('status', [RegistrationStatus::Registered->value, RegistrationStatus::Waitlist->value])
            ->whereHas('conversationTable', fn ($q) => $q->whereBetween('scheduled_at', [$weekStart, $weekEnd]))
            ->count();

        if ($registrationsThisWeek >= $this->settings->max_registrations_per_week) {
            return ['allowed' => false, 'reason' => 'weekly_limit_reached'];
        }

        $futureRegistrations = $user->registrations()
            ->whereIn('status', [RegistrationStatus::Registered->value, RegistrationStatus::Waitlist->value])
            ->whereHas('conversationTable', fn ($q) => $q->where('scheduled_at', '>', now()))
            ->count();

        if ($futureRegistrations >= $this->settings->max_future_registrations) {
            return ['allowed' => false, 'reason' => 'future_limit_reached'];
        }

        $alreadyRegistered = $user->registrations()
            ->where('conversation_table_id', $table->id)
            ->whereIn('status', [RegistrationStatus::Registered->value, RegistrationStatus::Waitlist->value])
            ->exists();

        if ($alreadyRegistered) {
            return ['allowed' => false, 'reason' => 'already_registered'];
        }

        if (! $forWaitlist) {
            $activeCard = $user->activeCards()->first();
            if (! $activeCard || ! $activeCard->hasSessionsRemaining()) {
                return ['allowed' => false, 'reason' => 'no_active_card'];
            }
        }

        return ['allowed' => true, 'reason' => null];
    }
}
