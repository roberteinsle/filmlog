<?php

namespace App\Livewire;

use App\Livewire\Forms\ViewingForm;
use App\Models\Companion;
use App\Models\Viewing;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Tagebuch: alle Sichtungen, neueste zuerst, gruppiert nach Monat. Bearbeiten und Löschen direkt in der Liste.
 */
#[Title('Tagebuch')]
class Diary extends Component
{
    private const int PER_PAGE = 50;

    public int $limit = self::PER_PAGE;

    public ViewingForm $form;

    public bool $showViewingDialog = false;

    /**
     * Die neuesten Sichtungen bis zum aktuellen Limit (plus eine, um „Mehr anzeigen“ zu erkennen).
     * viewing_number zählt die Sichtungen je Film chronologisch: 2 = „2. Sichtung“.
     *
     * @return Collection<int, Viewing>
     */
    #[Computed]
    public function viewings(): Collection
    {
        return Viewing::query()
            ->select('viewings.*')
            ->selectRaw('row_number() over (partition by tmdb_id order by watched_at, id) as viewing_number')
            ->with(['movie:tmdb_id,title,year,poster_path', 'companions'])
            ->orderByDesc('watched_at')
            ->orderByDesc('id')
            ->limit($this->limit + 1)
            ->get();
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
