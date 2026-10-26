<?php

namespace Database\Factories;

use App\Models\Companion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Companion>
 */
class CompanionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->firstName(),
            'max_fsk' => null,
            'sort' => fake()->numberBetween(1, 50),
        ];
    }
}
