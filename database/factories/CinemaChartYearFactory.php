<?php

namespace Database\Factories;

use App\Models\CinemaChartYear;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CinemaChartYear>
 */
class CinemaChartYearFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'year' => fake()->unique()->numberBetween(2000, 2024),
            'status' => CinemaChartYear::DONE,
            'fetched_at' => now(),
        ];
    }
}
