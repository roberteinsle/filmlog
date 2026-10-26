<?php

namespace App\Livewire;

use App\Console\Commands\RefreshCollections;
use App\Services\CollectionGaps;
use App\Services\ReviewStats;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Rückblick: Jahres- und Monatsstatistik, „Gegen den Strom“ und „Lücken“ in Filmreihen.
 */
#[Title('Rückblick')]
class Review extends Component
{
    #[Url(as: 'jahr', except: '')]
    public string $year = '';

    #[Url(as: 'monat', except: '')]
    public string $month = '';

    public bool $collectionsRequested = false;

    /**
     * @return list<int>
     */
    #[Computed]
    public function years(): array
    {
        return ReviewStats::years();
    }

    /**
     * Gewähltes Jahr; ohne (gültige) Auswahl das aktuelle, sonst das jüngste mit Sichtungen.
     */
    #[Computed]
    public function selectedYear(): ?int
    {
        $currentYear = (int) now(config('app.timezone'))->format('Y');

        return match (true) {
            in_array((int) $this->year, $this->years, true) => (int) $this->year,
            in_array($currentYear, $this->years, true) => $currentYear,
            default => $this->years[0] ?? null,
        };
    }

    #[Computed]
    public function selectedMonth(): ?int
    {
        $month = (int) $this->month;

        return $month >= 1 && $month <= 12 ? $month : null;
    }

    #[Computed]
    public function stats(): ?ReviewStats
    {
        return $this->selectedYear !== null ? new ReviewStats($this->selectedYear, $this->selectedMonth) : null;
    }

    public function chooseYear(int $year): void
    {
        $this->year = (string) $year;
        $this->month = '';
        unset($this->selectedYear, $this->selectedMonth, $this->stats);
    }

    public function chooseMonth(int $month): void
    {
        $this->month = $month >= 1 && $month <= 12 && $month !== $this->selectedMonth ? (string) $month : '';
        unset($this->selectedMonth, $this->stats);
    }

    /**
     * Filmreihen sofort laden statt erst in der Nacht (z. B. nach dem Trakt-Import).
     */
    public function loadCollections(): void
    {
        RefreshCollections::queueStale();
        $this->collectionsRequested = true;
    }

    public function render(CollectionGaps $collectionGaps): View
    {
        $pendingCollections = $collectionGaps->pendingCollections();

        return view('livewire.review', [
            'gaps' => $collectionGaps->all(),
            'pendingCollections' => $pendingCollections,
            'isLoadingCollections' => $this->collectionsRequested && $pendingCollections > 0,
        ]);
    }
}
