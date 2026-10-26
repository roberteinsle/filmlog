<?php

namespace Database\Factories;

use App\Models\TraktImport;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TraktImport>
 */
class TraktImportFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'original_name' => 'trakt-export.zip',
            'path' => 'imports/'.fake()->uuid().'.zip',
            'status' => TraktImport::PENDING,
        ];
    }
}
