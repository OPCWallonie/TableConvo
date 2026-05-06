<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    public function definition(): array
    {
        $totalHt = 206.61;
        $vatAmount = 43.39;

        return [
            'user_id' => User::factory(),
            'company_snapshot' => [
                'name' => fake()->company(),
                'vat_number' => 'BE0' . fake()->numerify('#########'),
                'street' => fake()->streetAddress(),
                'postal_code' => fake()->numerify('####'),
                'city' => fake()->city(),
            ],
            'total_ht' => $totalHt,
            'total_vat' => $vatAmount,
            'total_ttc' => $totalHt + $vatAmount,
            'status' => OrderStatus::Paid,
            'mollie_payment_id' => 'tr_' . fake()->regexify('[A-Za-z0-9]{10}'),
            'paid_at' => now(),
        ];
    }

    public function paid(): static
    {
        return $this->state(['status' => OrderStatus::Paid, 'paid_at' => now()]);
    }
}
