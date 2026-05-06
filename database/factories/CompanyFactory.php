<?php

namespace Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'vat_number' => 'BE0' . fake()->unique()->numerify('#########'),
            'street' => fake()->streetAddress(),
            'postal_code' => fake()->numerify('####'),
            'city' => fake()->city(),
            'country' => 'Belgique',
            'billing_email' => fake()->companyEmail(),
        ];
    }
}
