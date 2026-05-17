<?php

namespace Database\Factories;

use App\Enums\GlobalWaitlistEntryStatus;
use App\Enums\GlobalWaitlistSource;
use App\Models\GlobalWaitlistEntry;
use App\Models\Level;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GlobalWaitlistEntry>
 */
class GlobalWaitlistEntryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id'      => User::factory(),
            'level_id'     => Level::factory(),
            'requested_at' => now(),
            'source'       => GlobalWaitlistSource::AdminRemovedWaitlist,
            'admin_reason' => null,
            'created_by'   => User::factory(),
            'status'       => GlobalWaitlistEntryStatus::Pending,
        ];
    }

    public function pending(): static
    {
        return $this->state(['status' => GlobalWaitlistEntryStatus::Pending]);
    }

    public function reassigned(): static
    {
        return $this->state(['status' => GlobalWaitlistEntryStatus::Reassigned]);
    }

    public function dismissed(): static
    {
        return $this->state([
            'status'           => GlobalWaitlistEntryStatus::Dismissed,
            'dismissed_reason' => 'Retiré du vivier.',
            'dismissed_at'     => now(),
        ]);
    }

    public function fromCancellation(): static
    {
        return $this->state([
            'source'       => GlobalWaitlistSource::AdminCancelledRegistration,
            'admin_reason' => 'Annulation inscription confirmée.',
        ]);
    }

    public function waitingDays(int $days): static
    {
        return $this->state(['requested_at' => now()->subDays($days)]);
    }
}
