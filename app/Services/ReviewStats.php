<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Zahlen für den Rückblick eines Jahres oder Monats. Grundlage sind die Sichtungen im Zeitraum
 * (Ortszeit); ein zweimal gesehener Film zählt bei Sichtungen, Stunden, Genres und Regie doppelt.
 */
class ReviewStats
{
    public readonly CarbonImmutable $start;

    public readonly CarbonImmutable $end;

    public function __construct(public readonly int $year, public readonly ?int $month = null)
    {
        $this->start = CarbonImmutable::create($year, $month ?? 1, 1, 0, 0, 0, config('app.timezone'));
        $this->end = $month === null ? $this->start->addYear() : $this->start->addMonth();
    }

    /**
     * Jahre mit Sichtungen, neuestes zuerst.
     *
     * @return list<int>
     */
    public static function years(): array
    {
        return DB::table('viewings')
            ->selectRaw('distinct extract(year from watched_at at time zone ?)::int as year', [config('app.timezone')])
            ->orderByDesc('year')
            ->pluck('year')
            ->map(fn ($year): int => (int) $year)
            ->all();
    }

    /**
     * @return array{viewings: int, movies: int, minutes: int, average_rating: ?float}
     */
    public function summary(): array
    {
        $row = $this->viewings()
            ->selectRaw('count(*) as viewings, count(distinct viewings.tmdb_id) as movies, coalesce(sum(movies.runtime), 0) as minutes, avg(viewings.rating) as average_rating')
            ->first();

        return [
            'viewings' => (int) $row->viewings,
            'movies' => (int) $row->movies,
            'minutes' => (int) $row->minutes,
            'average_rating' => $row->average_rating !== null ? round((float) $row->average_rating, 1) : null,
        ];
    }

    /**
     * Sichtungen je Monat des Jahres (1–12, auch leere Monate).
     *
     * @return array<int, int>
     */
    public function viewingsPerMonth(): array
    {
        $counts = DB::table('viewings')
            ->selectRaw('extract(month from watched_at at time zone ?)::int as month, count(*) as viewings', [config('app.timezone')])
            ->where('watched_at', '>=', self::instant($this->start->startOfYear()))
            ->where('watched_at', '<', self::instant($this->start->startOfYear()->addYear()))
            ->groupBy('month')
            ->pluck('viewings', 'month');

        return collect(range(1, 12))->mapWithKeys(fn (int $month): array => [$month => (int) ($counts[$month] ?? 0)])->all();
    }

    /**
     * @return Collection<int, array{name: string, viewings: int}>
     */
    public function topGenres(int $limit = 5): Collection
    {
        $genreNames = config('filmlog.genres');

        return $this->viewings()
            ->crossJoin(DB::raw('jsonb_array_elements_text(movies.genre_ids) as genre(id)'))
            ->selectRaw('genre.id::int as genre_id, count(*) as viewings')
            ->groupBy('genre_id')
            ->orderByDesc('viewings')
            ->orderBy('genre_id')
            ->limit($limit)
            ->get()
            ->map(fn (object $row): array => ['name' => $genreNames[(int) $row->genre_id] ?? "Genre {$row->genre_id}", 'viewings' => (int) $row->viewings]);
    }

    /**
     * Lieblingsregie: nach Anzahl der Sichtungen bzw. nach Ø Bewertung (ab $minRatings bewerteten Sichtungen).
     *
     * @return array{by_count: Collection<int, array{name: string, viewings: int, average_rating: ?float}>, by_rating: Collection<int, array{name: string, viewings: int, average_rating: ?float}>}
     */
    public function favoriteDirectors(int $limit = 5, int $minRatings = 2): array
    {
        $directors = fn (): Builder => $this->viewings()
            ->crossJoin(DB::raw('jsonb_array_elements(movies.directors) as director'))
            ->selectRaw("director->>'id' as director_id, max(director->>'name') as name, count(*) as viewings, avg(viewings.rating) as average_rating, count(viewings.rating) as ratings")
            ->groupBy('director_id');

        $toArray = fn (object $row): array => [
            'name' => (string) $row->name,
            'viewings' => (int) $row->viewings,
            'average_rating' => $row->average_rating !== null ? round((float) $row->average_rating, 1) : null,
        ];

        return [
            'by_count' => $directors()->orderByDesc('viewings')->orderByDesc('average_rating')->orderBy('name')->limit($limit)->get()->map($toArray),
            'by_rating' => $directors()->havingRaw('count(viewings.rating) >= ?', [$minRatings])
                ->orderByDesc('average_rating')->orderByDesc('viewings')->orderBy('name')->limit($limit)->get()->map($toArray),
        ];
    }

    /**
     * „Gegen den Strom“: größte Abweichung der eigenen (jüngsten) Bewertung von TMDB, nur bei genug TMDB-Stimmen.
     *
     * @return Collection<int, array{tmdb_id: int, title: string, year: ?int, rating: int, tmdb_rating: float, difference: float}>
     */
    public function againstTheGrain(int $limit = 5): Collection
    {
        return $this->viewings()
            ->whereNotNull('viewings.rating')
            ->whereNotNull('movies.vote_average')
            ->where('movies.vote_count', '>=', config('filmlog.tmdb_min_votes'))
            ->selectRaw('distinct on (viewings.tmdb_id) viewings.tmdb_id, movies.title, movies.year, viewings.rating, movies.vote_average')
            ->orderBy('viewings.tmdb_id')
            ->orderByDesc('viewings.watched_at')
            ->get()
            ->map(fn (object $row): array => [
                'tmdb_id' => (int) $row->tmdb_id,
                'title' => (string) $row->title,
                'year' => $row->year !== null ? (int) $row->year : null,
                'rating' => (int) $row->rating,
                'tmdb_rating' => (float) $row->vote_average,
                'difference' => round((int) $row->rating - (float) $row->vote_average, 1),
            ])
            ->sortByDesc(fn (array $movie): float => abs($movie['difference']))
            ->take($limit)
            ->values();
    }

    private function viewings(): Builder
    {
        return DB::table('viewings')
            ->join('movies', 'movies.tmdb_id', '=', 'viewings.tmdb_id')
            ->where('viewings.watched_at', '>=', self::instant($this->start))
            ->where('viewings.watched_at', '<', self::instant($this->end));
    }

    /**
     * Zeitpunkt mit Offset, damit Postgres die Ortszeit nicht in der Zeitzone der Session (UTC) deutet.
     */
    private static function instant(CarbonImmutable $moment): string
    {
        return $moment->format('Y-m-d H:i:sP');
    }
}
