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

    /**
     * Speichert eine Filmreihe aus der TMDB-Antwort (/collection/{id}); Teile nach Erscheinungsdatum,
     * angekündigte Teile ohne Datum ans Ende.
     *
     * @param  array<string, mixed>  $data
     */
    public static function storeFromTmdb(int $collectionId, array $data): self
    {
        $parts = collect($data['parts'] ?? [])
            ->filter(fn (mixed $part): bool => is_array($part) && isset($part['id']))
            ->map(fn (array $part): array => [
                'id' => (int) $part['id'],
                'title' => (string) ($part['title'] ?? $part['original_title'] ?? ''),
                'release_date' => filled($part['release_date'] ?? null) ? (string) $part['release_date'] : null,
                'poster_path' => $part['poster_path'] ?? null,
            ])
            ->sortBy(fn (array $part): string => $part['release_date'] ?? '9999-99-99')
            ->values()
            ->all();

        return static::updateOrCreate(
            ['id' => $collectionId],
            ['name' => (string) ($data['name'] ?? 'Filmreihe'), 'parts' => $parts, 'cached_at' => now()],
        );
    }
}
