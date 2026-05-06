<?php

namespace Database\Factories;

use App\Models\CardType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CardType>
 */
class CardTypeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'Carte ' . fake()->randomNumber(2) . ' sessions',
            'sessions_count' => 10,
            'price' => 250.00,
            'validity_months' => 12,
            'is_active' => true,
        ];
    }
}
