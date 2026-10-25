<?php

namespace Database\Factories;

use App\Models\MovieList;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MovieList>
 */
class MovieListFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'is_watchlist' => false,
            'sort' => fake()->numberBetween(1, 50),
        ];
    }
}
