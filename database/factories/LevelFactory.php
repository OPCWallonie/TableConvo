<?php

namespace Database\Factories;

use App\Models\Level;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Level>
 */
class LevelFactory extends Factory
{
    private static int $sortOrder = 0;
    private static array $codes = ['A1', 'A2', 'B1', 'B2', 'C1', 'C2'];
    private static int $codeIndex = 0;

    public function definition(): array
    {
        $code = self::$codes[self::$codeIndex % count(self::$codes)] . '_' . fake()->unique()->randomNumber(3);
        self::$codeIndex++;

        return [
            'code' => substr($code, 0, 2) . fake()->unique()->randomLetter(),
            'name' => fake()->word(),
            'description' => fake()->sentence(),
            'sort_order' => ++self::$sortOrder,
            'is_active' => true,
        ];
    }

    public function withCode(string $code): static
    {
        return $this->state(['code' => $code]);
    }
}
