<?php

namespace App\Livewire;

use App\Jobs\ImportCinemaChart;
use App\Models\CinemaChartEntry;
use App\Models\CinemaChartYear;
use App\Models\StreamingSubscription;
use App\Services\InsideKino\InsideKinoCharts;
use App\Services\MovieStatus;
use App\Services\MovieStatusResolver;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Kinocharts Deutschland je Jahr (insidekino.de) mit Poster, TMDB-Bewertung, Plex und deinen Abos.
 */
#[Title('Kinocharts')]
class CinemaCharts extends Component
{
    /**
     * Läuft ein Import länger ohne Lebenszeichen, wird er neu angestoßen.
     */
    private const int STALE_IMPORT_MINUTES = 3;

    #[Url(as: 'jahr', except: '')]
    public string $year = '';

    /**
     * @return list<int>
     */
    #[Computed]
    public function years(): array
    {
        return range($this->currentYear(), InsideKinoCharts::FIRST_YEAR);
    }

    #[Computed]
    public function selectedYear(): int
    {
        return in_array((int) $this->year, $this->years, true) ? (int) $this->year : $this->currentYear();
    }

    /**
     * @return Collection<int, CinemaChartEntry>
     */
    #[Computed]
    public function entries(): Collection
    {
        return CinemaChartEntry::query()->where('year', $this->selectedYear)->with('movie')->orderBy('rank')->get();
    }

    /**
     * @return array<int, MovieStatus>
     */
    #[Computed]
    public function statuses(): array
    {
        return app(MovieStatusResolver::class)->forMany($this->entries->pluck('tmdb_id')->filter()->values()->all());
    }

    /**
     * @return Collection<int, StreamingSubscription>
     */
    #[Computed]
    public function subscriptions(): Collection
    {
        return StreamingSubscription::query()->orderBy('name')->get();
    }

    public function chooseYear(int $year): void
    {
        $this->year = in_array($year, $this->years, true) ? (string) $year : '';
        unset($this->selectedYear, $this->entries, $this->statuses);
    }

    public function render(): View
    {
        $chart = $this->ensureImport();

        return view('livewire.cinema-charts', [
            'chart' => $chart,
            'isLoading' => in_array($chart->status, [CinemaChartYear::PENDING, CinemaChartYear::LOADING], true),
            'unresolvedCount' => $this->entries->whereNull('resolved_at')->count(),
            'sourceUrl' => InsideKinoCharts::url($this->selectedYear),
        ]);
    }

    /**
     * Stößt den Import an, wenn das Jahr fehlt, veraltet ist oder nicht fertig zugeordnet wurde.
     */
    private function ensureImport(): CinemaChartYear
    {
        $chart = CinemaChartYear::firstOrCreate(['year' => $this->selectedYear]);

        $isRunning = $chart->status === CinemaChartYear::LOADING && $chart->updated_at->gt(now()->subMinutes(self::STALE_IMPORT_MINUTES));
        $isIncomplete = $chart->status !== CinemaChartYear::FAILED && $this->entries->whereNull('resolved_at')->isNotEmpty();

        if (! $isRunning && ($chart->status === CinemaChartYear::PENDING || $chart->needsFetch() || $isIncomplete)) {
            $chart->update(['status' => CinemaChartYear::LOADING]);
            ImportCinemaChart::dispatch($chart->year);
        }

        return $chart;
    }

    private function currentYear(): int
    {
        return (int) now(config('app.timezone'))->format('Y');
    }
}
