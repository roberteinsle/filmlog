<?php

namespace Database\Factories;

use App\Models\PlexAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlexAccount>
 */
class PlexAccountFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => fake()->unique()->numberBetween(1, 99_999_999),
            'title' => fake()->firstName(),
            'counts_viewings' => false,
            'last_seen_at' => now(),
        ];
    }
}
