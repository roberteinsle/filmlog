<?php

namespace App\Livewire;

use App\Livewire\Forms\ViewingForm;
use App\Models\Companion;
use App\Models\Viewing;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Tagebuch: alle Sichtungen, neueste zuerst, gruppiert nach Monat. Bearbeiten und Löschen direkt in der Liste.
 */
#[Title('Tagebuch')]
class Diary extends Component
{
    private const int PER_PAGE = 50;

    /**
     * Filter nach Sichtung: '' = alle, '1' = Erstsichtungen, 'wdh' = alle Wiederholungen, '2', '3', … = genau die n. Sichtung.
     */
    #[Url(as: 'sichtung', except: '')]
    public string $viewingFilter = '';

    public int $limit = self::PER_PAGE;

    public ViewingForm $form;

    public bool $showViewingDialog = false;

    /**
     * Die neuesten Sichtungen (gefiltert) bis zum aktuellen Limit (plus eine, um „Mehr anzeigen“ zu erkennen).
     * viewing_number zählt die Sichtungen je Film chronologisch: 2 = „2. Sichtung“.
     *
     * @return Collection<int, Viewing>
     */
    #[Computed]
    public function viewings(): Collection
    {
        return $this->numberedViewings()
            ->when($this->viewingFilter === 'wdh', fn (Builder $query) => $query->where('viewing_number', '>=', 2))
            ->when(ctype_digit($this->viewingFilter), fn (Builder $query) => $query->where('viewing_number', (int) $this->viewingFilter))
            ->with(['movie:tmdb_id,title,year,poster_path', 'companions'])
            ->orderByDesc('watched_at')
            ->orderByDesc('id')
            ->limit($this->limit + 1)
            ->get();
    }

    /**
     * Höchste Sichtungszahl eines Films (für die Filter-Auswahl).
     */
    #[Computed]
    public function maxViewingNumber(): int
    {
        return (int) DB::query()->fromSub(DB::table('viewings')->selectRaw('count(*) as viewings')->groupBy('tmdb_id'), 'counts')->max('viewings');
    }

    public function filterViewings(string $filter): void
    {
        $this->viewingFilter = $filter === 'wdh' || (ctype_digit($filter) && (int) $filter >= 1 && (int) $filter <= $this->maxViewingNumber) ? $filter : '';
        $this->limit = self::PER_PAGE;
        unset($this->viewings);
    }

    /**
     * @return Collection<int, Companion>
     */
    #[Computed]
    public function companions(): Collection
    {
        return Companion::query()->ordered()->get();
    }

    public function showMore(): void
    {
        $this->limit += self::PER_PAGE;
    }

    public function editViewing(int $viewingId): void
    {
        $this->form->edit(Viewing::findOrFail($viewingId));
        $this->showViewingDialog = true;
    }

    public function updateViewing(): void
    {
        if ($this->form->viewing === null) {
            $this->closeViewingDialog();

            return;
        }

        $this->form->update();
        $this->closeViewingDialog();
    }

    public function closeViewingDialog(): void
    {
        $this->showViewingDialog = false;
        $this->form->viewing = null;
    }

    public function deleteViewing(int $viewingId): void
    {
        Viewing::findOrFail($viewingId)->delete();
    }

    /**
     * Sichtungen mit viewing_number (1 = erste Sichtung des Films, 2 = zweite, …), filterbar wie eine Tabelle.
     *
     * @return Builder<Viewing>
     */
    private function numberedViewings(): Builder
    {
        return Viewing::query()->fromSub(
            Viewing::query()->select('viewings.*')->selectRaw('row_number() over (partition by tmdb_id order by watched_at, id) as viewing_number'),
            'viewings',
        );
    }

    public function render(): View
    {
        $viewings = $this->viewings;

        return view('livewire.diary', [
            'months' => $viewings->take($this->limit)
                ->groupBy(fn (Viewing $viewing): string => $viewing->watched_at->timezone(config('app.timezone'))->format('Y-m')),
            'hasMore' => $viewings->count() > $this->limit,
        ]);
    }
}
