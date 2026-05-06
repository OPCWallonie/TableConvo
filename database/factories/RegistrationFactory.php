<?php

namespace Database\Factories;

use App\Enums\RegistrationStatus;
use App\Models\Card;
use App\Models\ConversationTable;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Registration>
 */
class RegistrationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'conversation_table_id' => ConversationTable::factory(),
            'card_id' => null,
            'status' => RegistrationStatus::Registered,
            'registered_at' => now(),
        ];
    }

    public function confirmed(): static
    {
        return $this->state(['status' => RegistrationStatus::Registered]);
    }

    public function onWaitlist(int $position = 1): static
    {
        return $this->state([
            'status' => RegistrationStatus::Waitlist,
            'card_id' => null,
            'waitlist_position' => $position,
        ]);
    }
}
