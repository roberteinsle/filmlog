<?php

namespace App\Livewire;

use App\Actions\SyncMovie;
use App\Models\Movie;
use App\Models\MovieList;
use App\Services\Tmdb\MovieDetails;
use App\Services\Tmdb\TmdbClient;
use App\Services\Tmdb\TmdbException;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * „Zur Liste“-Button mit Dialog: Film auf Listen setzen oder herunternehmen, auch direkt in einer neuen Liste.
 * Meldet jede Änderung mit dem Event list-membership-changed, damit Banner und Status-Punkte nachziehen.
 */
class AddToList extends Component
{
    #[Locked]
    public int $tmdbId;

    #[Locked]
    public string $variant = 'button';

    public bool $open = false;

    public string $newListName = '';

    public ?string $error = null;

    /**
     * @return Collection<int, MovieList>
     */
    #[Computed]
    public function lists(): Collection
    {
        return MovieList::query()->ordered()->get();
    }

    /**
     * IDs der Listen, auf denen der Film steht.
     *
     * @return list<int>
     */
    #[Computed]
    public function memberListIds(): array
    {
        return DB::table('list_items')->where('tmdb_id', $this->tmdbId)->pluck('list_id')->map(fn ($id): int => (int) $id)->all();
    }

    /**
     * Öffnet den Dialog. Ein Film aus der Suche steht noch nicht im lokalen Cache; dann wird er zuerst von TMDB geholt.
     */
    public function openDialog(TmdbClient $tmdb, SyncMovie $syncMovie): void
    {
        $this->error = null;

        if (! Movie::query()->whereKey($this->tmdbId)->exists()) {
            try {
                $syncMovie->handle(MovieDetails::fromTmdb($tmdb->movie($this->tmdbId), $tmdb->region()));
            } catch (TmdbException $exception) {
                report($exception);
                $this->error = 'TMDB ist gerade nicht erreichbar. Bitte versuch es gleich noch einmal.';

                return;
            }
        }

        $this->resetValidation();
        $this->newListName = '';
        $this->open = true;
    }

    public function closeDialog(): void
    {
        $this->open = false;
    }

    public function toggleList(int $listId): void
    {
        $list = MovieList::findOrFail($listId);

        if ($list->contains($this->tmdbId)) {
            $list->removeMovie($this->tmdbId);
        } else {
            $list->addMovie($this->tmdbId);
        }

        $this->membershipChanged();
    }

    public function createList(): void
    {
        $validated = $this->validate([
            'newListName' => ['required', 'string', 'max:50', Rule::unique('lists', 'name')],
        ], attributes: ['newListName' => 'Name']);

        $list = MovieList::create([
            'name' => trim($validated['newListName']),
            'is_watchlist' => false,
            'sort' => (int) MovieList::query()->max('sort') + 1,
        ]);
        $list->addMovie($this->tmdbId);

        $this->newListName = '';
        unset($this->lists);
        $this->membershipChanged();
    }

    public function render(): View
    {
        return view('livewire.add-to-list');
    }

    private function membershipChanged(): void
    {
        unset($this->memberListIds);
        $this->dispatch('list-membership-changed', tmdbId: $this->tmdbId);
    }
}
