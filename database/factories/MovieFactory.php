<?php

namespace Database\Factories;

use App\Models\Movie;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Movie>
 */
class MovieFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tmdb_id' => fake()->unique()->numberBetween(1, 2_000_000),
            'title' => fake()->sentence(3),
            'original_title' => null,
            'year' => fake()->numberBetween(1960, 2026),
            'runtime' => fake()->numberBetween(80, 180),
            'fsk' => fake()->randomElement([0, 6, 12, 16, 18]),
            'poster_path' => '/'.fake()->lexify('????????????').'.jpg',
            'vote_average' => fake()->randomFloat(1, 4, 9),
            'vote_count' => fake()->numberBetween(0, 30_000),
            'genre_ids' => [fake()->randomElement([18, 28, 35, 53, 878])],
            'directors' => [['id' => fake()->numberBetween(1, 99_999), 'name' => fake()->name()]],
            'cached_at' => now(),
        ];
    }
}
