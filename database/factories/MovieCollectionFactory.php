<?php

namespace Database\Factories;

use App\Models\MovieCollection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MovieCollection>
 */
class MovieCollectionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => fake()->unique()->numberBetween(1, 9_999_999),
            'name' => fake()->words(2, true).' – Filmreihe',
            'parts' => [],
            'cached_at' => now(),
        ];
    }
}
