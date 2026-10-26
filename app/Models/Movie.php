<?php

namespace App\Models;

use Database\Factories\MovieFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Lokaler Cache eines TMDB-Films (nur Felder für Filter, Statistik und Listen).
 */
#[Table(key: 'tmdb_id', incrementing: false)]
#[Fillable([
    'tmdb_id', 'title', 'original_title', 'year', 'runtime', 'fsk', 'poster_path',
    'vote_average', 'vote_count', 'genre_ids', 'directors', 'provider_ids', 'collection_id', 'imdb_id', 'cached_at',
])]
class Movie extends Model
{
    /** @use HasFactory<MovieFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tmdb_id' => 'integer',
            'year' => 'integer',
            'runtime' => 'integer',
            'fsk' => 'integer',
            'vote_average' => 'float',
            'vote_count' => 'integer',
            'genre_ids' => 'array',
            'directors' => 'array',
            'provider_ids' => 'array',
            'collection_id' => 'integer',
            'on_plex' => 'boolean',
            'cached_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<Viewing, $this>
     */
    public function viewings(): HasMany
    {
        return $this->hasMany(Viewing::class, 'tmdb_id');
    }

    /**
     * @return BelongsToMany<MovieList, $this>
     */
    public function lists(): BelongsToMany
    {
        return $this->belongsToMany(MovieList::class, 'list_items', 'tmdb_id', 'list_id')
            ->withPivot('rank', 'added_at');
    }
}
