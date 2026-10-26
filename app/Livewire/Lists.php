<?php

namespace App\Livewire;

use App\Models\MovieList;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Listen-Übersicht: Watchlist oben, darunter die eigenen Listen (anlegen, umbenennen, sortieren, löschen).
 */
#[Title('Listen')]
class Lists extends Component
{
    private const int PREVIEW_POSTERS = 4;

    public string $name = '';

    public ?int $editingId = null;

    public string $editName = '';

    /**
     * @return Collection<int, MovieList>
     */
    #[Computed]
    public function lists(): Collection
    {
        return MovieList::query()
            ->ordered()
            ->withCount('movies')
            ->with(['movies' => fn ($query) => $query->select('movies.tmdb_id', 'movies.title', 'movies.poster_path')->limit(self::PREVIEW_POSTERS)])
            ->get();
    }

    public function createList(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:50', Rule::unique('lists', 'name')],
        ], attributes: ['name' => 'Name']);

        $list = MovieList::create([
            'name' => trim($validated['name']),
            'is_watchlist' => false,
            'sort' => (int) MovieList::query()->max('sort') + 1,
        ]);

        $this->redirectRoute('lists.show', $list, navigate: true);
    }

    public function startEditing(int $listId): void
    {
        $list = $this->ownList($listId);

        $this->resetErrorBag();
        $this->editingId = $list->id;
        $this->editName = $list->name;
    }

    public function saveList(): void
    {
        $list = $this->ownList((int) $this->editingId);

        $validated = $this->validate([
            'editName' => ['required', 'string', 'max:50', Rule::unique('lists', 'name')->ignore($list->id)],
        ], attributes: ['editName' => 'Name']);

        $list->update(['name' => trim($validated['editName'])]);

        $this->cancelEditing();
        unset($this->lists);
    }

    public function cancelEditing(): void
    {
        $this->reset('editingId', 'editName');
        $this->resetErrorBag();
    }

    public function deleteList(int $listId): void
    {
        $this->ownList($listId)->delete();

        if ($this->editingId === $listId) {
            $this->cancelEditing();
        }

        unset($this->lists);
    }

    /**
     * Handler für wire:sort der eigenen Listen; die Watchlist bleibt immer oben.
     */
    public function moveList(int|string $listId, int $position): void
    {
        $ids = MovieList::query()->where('is_watchlist', false)->ordered()->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $listId = (int) $listId;

        if (! in_array($listId, $ids, true)) {
            return;
        }

        $ids = array_values(array_diff($ids, [$listId]));
        array_splice($ids, max(0, min($position, count($ids))), 0, [$listId]);

        DB::transaction(function () use ($ids): void {
            foreach ($ids as $index => $id) {
                MovieList::whereKey($id)->update(['sort' => $index + 1]);
            }
        });

        unset($this->lists);
    }

    public function render(): View
    {
        return view('livewire.lists');
    }

    /**
     * Eine eigene Liste; die Watchlist lässt sich weder umbenennen noch löschen.
     */
    private function ownList(int $listId): MovieList
    {
        return MovieList::query()->where('is_watchlist', false)->findOrFail($listId);
    }
}
