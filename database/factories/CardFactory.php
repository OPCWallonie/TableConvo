<?php

namespace Database\Factories;

use App\Enums\CardStatus;
use App\Models\Card;
use App\Models\CardType;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Card>
 */
class CardFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'card_type_id' => CardType::factory(),
            'order_id' => Order::factory(),
            'sessions_total' => 10,
            'sessions_remaining' => 10,
            'price_paid' => 250.00,
            'purchased_at' => now(),
            'expires_at' => now()->addMonths(12),
            'status' => CardStatus::Active,
        ];
    }

    public function expiringSoon(int $daysLeft = 15): static
    {
        return $this->state([
            'expires_at' => now()->addDays($daysLeft),
            'status' => CardStatus::Active,
        ]);
    }

    public function expired(): static
    {
        return $this->state([
            'expires_at' => now()->subDay(),
            'status' => CardStatus::Expired,
        ]);
    }

    public function withSessions(int $remaining): static
    {
        return $this->state(['sessions_remaining' => $remaining]);
    }
}
