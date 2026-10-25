<?php

namespace Database\Factories;

use App\Models\Movie;
use App\Models\Viewing;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Viewing>
 */
class ViewingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tmdb_id' => Movie::factory(),
            'watched_at' => fake()->dateTimeBetween('-5 years'),
            'rating' => fake()->numberBetween(1, 10),
            'note' => null,
            'source' => 'manual',
        ];
    }
}
