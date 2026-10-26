<?php

namespace App\Models;

use Database\Factories\MovieListFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;

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

    /**
     * Watchlist zuerst, danach die eigenen Listen in ihrer Reihenfolge.
     *
     * @param  Builder<self>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderByDesc('is_watchlist')->orderBy('sort')->orderBy('id');
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

    public function contains(int $tmdbId): bool
    {
        return $this->movies()->wherePivot('tmdb_id', $tmdbId)->exists();
    }

    /**
     * Hängt einen Film ans Ende der Liste; steht er schon darauf, passiert nichts.
     */
    public function addMovie(int $tmdbId): void
    {
        DB::table('list_items')->insertOrIgnore([
            'list_id' => $this->id,
            'tmdb_id' => $tmdbId,
            'rank' => (int) DB::table('list_items')->where('list_id', $this->id)->max('rank') + 1,
            'added_at' => now(),
        ]);
    }

    public function removeMovie(int $tmdbId): void
    {
        $this->movies()->detach($tmdbId);
    }

    /**
     * Verschiebt einen Film an eine neue (nullbasierte) Position und nummeriert den Rang neu (1, 2, 3, …).
     */
    public function moveMovie(int $tmdbId, int $position): void
    {
        DB::transaction(function () use ($tmdbId, $position): void {
            $tmdbIds = DB::table('list_items')
                ->where('list_id', $this->id)
                ->orderBy('rank')
                ->orderBy('added_at')
                ->lockForUpdate()
                ->pluck('tmdb_id')
                ->map(fn ($id): int => (int) $id)
                ->all();

            if (! in_array($tmdbId, $tmdbIds, true)) {
                return;
            }

            $tmdbIds = array_values(array_diff($tmdbIds, [$tmdbId]));
            array_splice($tmdbIds, max(0, min($position, count($tmdbIds))), 0, [$tmdbId]);

            foreach ($tmdbIds as $index => $id) {
                DB::table('list_items')->where('list_id', $this->id)->where('tmdb_id', $id)->update(['rank' => $index + 1]);
            }
        });
    }
}
