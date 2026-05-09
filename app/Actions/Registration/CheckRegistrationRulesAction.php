<?php

namespace App\Actions\Registration;

use App\Enums\RegistrationStatus;
use App\Enums\SessionStatus;
use App\Models\ConversationTable;
use App\Models\User;
use App\Settings\BookingSettings;
use Carbon\Carbon;

class CheckRegistrationRulesAction
{
    public function __construct(private readonly BookingSettings $settings) {}

    /**
     * @return array{allowed: bool, reason: string|null}
     */
    public function execute(User $user, ConversationTable $table, bool $forWaitlist = false): array
    {
        if ($table->status !== SessionStatus::Scheduled) {
            return ['allowed' => false, 'reason' => 'session_not_open_for_registration'];
        }

        if ($table->scheduled_at->isPast()) {
            return ['allowed' => false, 'reason' => 'session_already_passed'];
        }

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

        // Doublon : vérifier EN PREMIER avant les quotas pour donner l'erreur la plus précise
        $alreadyRegistered = $user->registrations()
            ->where('conversation_table_id', $table->id)
            ->whereIn('status', [RegistrationStatus::Registered->value, RegistrationStatus::Waitlist->value])
            ->exists();

        if ($alreadyRegistered) {
            return ['allowed' => false, 'reason' => 'already_registered'];
        }

        if (! $forWaitlist && $table->isFull()) {
            return ['allowed' => false, 'reason' => 'table_full'];
        }

        // Compte les inscriptions pour la MÊME semaine calendaire que la session cible
        $weekStart = $table->scheduled_at->copy()->startOfWeek();
        $weekEnd   = $table->scheduled_at->copy()->endOfWeek();
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

        if (! $forWaitlist) {
            $activeCard = $user->activeCards()->first();
            if (! $activeCard || ! $activeCard->hasSessionsRemaining()) {
                return ['allowed' => false, 'reason' => 'no_active_card'];
            }
        }

        return ['allowed' => true, 'reason' => null];
    }
}
