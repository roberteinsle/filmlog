<?php

namespace Database\Factories;

use App\Models\CinemaChartEntry;
use App\Models\CinemaChartYear;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CinemaChartEntry>
 */
class CinemaChartEntryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'year' => CinemaChartYear::factory(),
            'rank' => fake()->unique()->numberBetween(1, 200),
            'title' => fake()->words(3, true),
            'subtitle' => null,
            'visitors' => fake()->numberBetween(100_000, 5_000_000),
            'imdb_id' => null,
            'tmdb_id' => null,
            'resolved_at' => null,
        ];
    }
}
