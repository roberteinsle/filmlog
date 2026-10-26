<?php

namespace App\Models;

use Database\Factories\MovieCollectionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Filmreihe von TMDB (z. B. „Matrix – Filmreihe“) mit allen Teilen.
 *
 * @property list<array{id: int, title: string, release_date: ?string, poster_path: ?string}> $parts nach Erscheinungsdatum
 */
#[Table('collections', incrementing: false)]
#[Fillable(['id', 'name', 'parts', 'cached_at'])]
class MovieCollection extends Model
{
    /** @use HasFactory<MovieCollectionFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = ['parts' => '[]'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'parts' => 'array',
            'cached_at' => 'datetime',
        ];
    }

    /**
     * Teile, die bis heute erschienen sind (ohne Datum = angekündigt, zählt nicht).
     *
     * @return list<array{id: int, title: string, release_date: ?string, poster_path: ?string}>
     */
    public function releasedParts(): array
    {
        $today = now(config('app.timezone'))->toDateString();

        return array_values(array_filter($this->parts, fn (array $part): bool => filled($part['release_date']) && $part['release_date'] <= $today));
    }
}
