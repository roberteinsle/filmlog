<?php

namespace App\Models;

use Database\Factories\MovieListFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Watchlist oder eigene Liste. Die Watchlist legt die Migration an; es gibt genau eine.
 */
#[Table('lists')]
#[Fillable(['name', 'is_watchlist', 'sort'])]
class MovieList extends Model
{
    /** @use HasFactory<MovieListFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_watchlist' => 'boolean',
            'sort' => 'integer',
        ];
    }

    public static function watchlist(): self
    {
        return static::query()->where('is_watchlist', true)->firstOrFail();
    }

    /**
     * @return BelongsToMany<Movie, $this>
     */
    public function movies(): BelongsToMany
    {
        return $this->belongsToMany(Movie::class, 'list_items', 'list_id', 'tmdb_id')
            ->withPivot('rank', 'added_at')
            ->orderByPivot('rank');
    }
}
