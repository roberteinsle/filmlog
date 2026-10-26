<?php

namespace App\Jobs;

use App\Actions\EnsureMovie;
use App\Models\CinemaChartEntry;
use App\Models\CinemaChartYear;
use App\Services\InsideKino\ChartNotFoundException;
use App\Services\InsideKino\InsideKinoCharts;
use App\Services\Tmdb\TmdbClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Lädt die Kinocharts eines Jahres von insidekino.de und ordnet die Filme TMDB zu: per IMDb-ID, sonst per
 * Titelsuche im Chart-Jahr (bzw. Vorjahr). Läuft in Etappen à TIME_BUDGET Sekunden und reiht sich selbst neu ein.
 */
class ImportCinemaChart implements ShouldQueue
{
    use Queueable;

    private const int TIME_BUDGET = 50;

    /**
     * Pause zwischen TMDB-Zuordnungen (Rate-Limit).
     */
    private const int TMDB_PAUSE_MICROSECONDS = 30_000;

    public int $timeout = 85;

    public int $tries = 3;

    /**
     * @var list<int>
     */
    public array $backoff = [60, 300];

    public function __construct(public int $year) {}

    public function handle(InsideKinoCharts $charts, TmdbClient $tmdb, EnsureMovie $ensureMovie): void
    {
        $deadline = microtime(true) + self::TIME_BUDGET;
        $chart = CinemaChartYear::firstOrCreate(['year' => $this->year]);
        $chart->update(['status' => CinemaChartYear::LOADING, 'error' => null]);

        if ($chart->needsFetch()) {
            try {
                $this->storeEntries($chart, $charts->fetch($this->year));
            } catch (ChartNotFoundException $exception) {
                $chart->update(['status' => CinemaChartYear::FAILED, 'error' => $exception->getMessage(), 'fetched_at' => now()]);

                return;
            }
        }

        foreach ($chart->entries()->whereNull('resolved_at')->get() as $entry) {
            if (microtime(true) >= $deadline) {
                $chart->touch();
                self::dispatch($this->year);

                return;
            }

            $entry->update(['tmdb_id' => $this->resolve($entry, $tmdb, $ensureMovie), 'resolved_at' => now()]);
            usleep(self::TMDB_PAUSE_MICROSECONDS);
        }

        $chart->update(['status' => CinemaChartYear::DONE]);
    }

    public function failed(?Throwable $exception): void
    {
        CinemaChartYear::query()->whereKey($this->year)->update([
            'status' => CinemaChartYear::FAILED,
            'error' => $exception?->getMessage() ?? 'Unbekannter Fehler',
        ]);
    }

    /**
     * Speichert die Zeilen; bleibt der Titel auf einem Rang gleich, bleibt auch die TMDB-Zuordnung.
     *
     * @param  list<array{rank: int, title: string, subtitle: ?string, visitors: ?int, imdb_id: ?string}>  $rows
     */
    private function storeEntries(CinemaChartYear $chart, array $rows): void
    {
        DB::transaction(function () use ($chart, $rows): void {
            $existing = $chart->entries()->get()->keyBy('rank');

            foreach ($rows as $row) {
                $entry = $existing->get($row['rank']);

                if ($entry !== null && $entry->title === $row['title']) {
                    $entry->update(['visitors' => $row['visitors'], 'subtitle' => $row['subtitle'], 'imdb_id' => $row['imdb_id'] ?? $entry->imdb_id]);

                    continue;
                }

                CinemaChartEntry::updateOrCreate(
                    ['year' => $chart->year, 'rank' => $row['rank']],
                    $row + ['tmdb_id' => null, 'resolved_at' => null],
                );
            }

            $chart->entries()->whereNotIn('rank', array_column($rows, 'rank'))->delete();
            $chart->update(['fetched_at' => now()]);
        });
    }

    private function resolve(CinemaChartEntry $entry, TmdbClient $tmdb, EnsureMovie $ensureMovie): ?int
    {
        if ($entry->imdb_id !== null && ($tmdbId = $ensureMovie->handle(null, $entry->imdb_id)) !== null) {
            return $tmdbId;
        }

        // Ein Film startet oft im Vorjahr und läuft bis ins Chart-Jahr hinein.
        foreach ([$entry->year, $entry->year - 1, null] as $year) {
            $hit = $tmdb->searchMovies(Str::limit($entry->title, 100, ''), 1, $year)['results'][0] ?? null;

            if ($hit !== null) {
                return $ensureMovie->handle((int) $hit['id']);
            }
        }

        return null;
    }
}
