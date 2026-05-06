<?php

namespace Database\Factories;

use App\Enums\SessionStatus;
use App\Models\ConversationTable;
use App\Models\Level;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ConversationTable>
 */
class ConversationTableFactory extends Factory
{
    public function definition(): array
    {
        return [
            'level_id' => Level::factory(),
            'topic' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'scheduled_at' => now()->addDays(7),
            'duration_minutes' => 90,
            'max_participants' => 8,
            'location' => fake()->address(),
            'status' => SessionStatus::Scheduled,
        ];
    }

    public function upcoming(int $daysAhead = 7): static
    {
        return $this->state(['scheduled_at' => now()->addDays($daysAhead)]);
    }

    public function full(int $maxParticipants = 2): static
    {
        return $this->state(['max_participants' => $maxParticipants]);
    }

    public function cancelled(): static
    {
        return $this->state([
            'status' => SessionStatus::Cancelled,
            'cancelled_at' => now(),
            'cancellation_reason' => 'Annulé pour test',
        ]);
    }
}
