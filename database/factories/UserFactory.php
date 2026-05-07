<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Level;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function withLevel(?Level $level = null): static
    {
        return $this->state(function () use ($level) {
            $level ??= Level::factory()->create();
            return [
                'level_id' => $level->id,
                'level_assigned_at' => now(),
            ];
        });
    }

    public function withCompany(?Company $company = null): static
    {
        return $this->state(function () use ($company) {
            $company ??= Company::factory()->create();
            return ['company_id' => $company->id];
        });
    }
}
