<?php

namespace Database\Factories;

use App\Models\StreamingSubscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StreamingSubscription>
 */
class StreamingSubscriptionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'provider_id' => fake()->unique()->numberBetween(1, 9999),
            'name' => fake()->company(),
            'logo_path' => null,
        ];
    }
}
