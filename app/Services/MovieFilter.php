<?php

namespace App\Services;

use App\Models\Movie;
use Illuminate\Database\Eloquent\Builder;

/**
 * Kombinierbare Filter für Filmlisten. Kommt aus der URL; ungültige Werte werden ignoriert, nicht abgelehnt.
 */
final readonly class MovieFilter
{
    /**
     * Auswahl für „TMDB ≥ X“.
     */
    public const array TMDB_LEVELS = [6, 6.5, 7, 7.5, 8];

    /**
     * Auswahl für „Laufzeit bis X Minuten“.
     */
    public const array RUNTIME_LIMITS = [90, 100, 120, 130, 150];

    /**
     * Auswahl für „bis FSK X“; Filme ohne bekannte Freigabe fallen dann heraus.
     */
    public const array FSK_LEVELS = [0, 6, 12, 16];

    public function __construct(
        public ?int $minOwnRating = null,
        public ?float $minTmdbRating = null,
        public ?int $genreId = null,
        public ?int $decade = null,
        public ?int $maxRuntime = null,
        public ?int $maxFsk = null,
    ) {}

    public static function fromInput(
        ?string $minOwnRating = null,
        ?string $minTmdbRating = null,
        ?string $genreId = null,
        ?string $decade = null,
        ?string $maxRuntime = null,
        ?string $maxFsk = null,
    ): self {
        $minOwnRating = self::toInt($minOwnRating);
        $minTmdbRating = is_numeric($minTmdbRating) ? (float) $minTmdbRating : null;
        $genreId = self::toInt($genreId);
        $decade = self::toInt($decade);
        $maxRuntime = self::toInt($maxRuntime);
        $maxFsk = self::toInt($maxFsk);

        return new self(
            minOwnRating: $minOwnRating !== null && $minOwnRating >= 1 && $minOwnRating <= 10 ? $minOwnRating : null,
            minTmdbRating: in_array($minTmdbRating, self::TMDB_LEVELS, false) ? $minTmdbRating : null,
            genreId: array_key_exists((int) $genreId, config('filmlog.genres')) ? $genreId : null,
            decade: $decade !== null && $decade >= 1870 && $decade <= 2100 && $decade % 10 === 0 ? $decade : null,
            maxRuntime: in_array($maxRuntime, self::RUNTIME_LIMITS, true) ? $maxRuntime : null,
            maxFsk: in_array($maxFsk, self::FSK_LEVELS, true) ? $maxFsk : null,
        );
    }

    public function isActive(): bool
    {
        return $this != new self;
    }

    /**
     * @param  Builder<Movie>  $query
     * @return Builder<Movie>
     */
    public function apply(Builder $query): Builder
    {
        return $query
            ->when($this->minOwnRating !== null, fn (Builder $query) => $query->whereRaw(
                '(select v.rating from viewings v where v.tmdb_id = movies.tmdb_id and v.rating is not null order by v.watched_at desc, v.id desc limit 1) >= ?',
                [$this->minOwnRating],
            ))
            ->when($this->minTmdbRating !== null, fn (Builder $query) => $query
                ->where('movies.vote_average', '>=', $this->minTmdbRating)
                ->where('movies.vote_count', '>=', config('filmlog.tmdb_min_votes')))
            ->when($this->genreId !== null, fn (Builder $query) => $query->whereJsonContains('movies.genre_ids', $this->genreId))
            ->when($this->decade !== null, fn (Builder $query) => $query->whereBetween('movies.year', [$this->decade, $this->decade + 9]))
            ->when($this->maxRuntime !== null, fn (Builder $query) => $query->where('movies.runtime', '<=', $this->maxRuntime))
            ->when($this->maxFsk !== null, fn (Builder $query) => $query->where('movies.fsk', '<=', $this->maxFsk));
    }

    private static function toInt(?string $value): ?int
    {
        return $value !== null && preg_match('/^\d{1,5}$/', $value) ? (int) $value : null;
    }
}
