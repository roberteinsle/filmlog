<?php

namespace App\Livewire;

use App\Jobs\SyncPlexLibrary;
use App\Models\Companion;
use App\Models\Movie;
use App\Services\Plex\PlexClient;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Einstellungen: Begleitungen anlegen, bearbeiten, sortieren (Drag & Drop) und löschen.
 */
#[Title('Einstellungen')]
class Settings extends Component
{
    public string $name = '';

    public string $maxFsk = '';

    public bool $plexSyncQueued = false;

    public ?int $editingId = null;

    public string $editName = '';

    public string $editMaxFsk = '';

    /**
     * @return Collection<int, Companion>
     */
    #[Computed]
    public function companions(): Collection
    {
        return Companion::query()->ordered()->withCount('viewings')->get();
    }

    public function addCompanion(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:50', Rule::unique('companions', 'name')],
            'maxFsk' => ['nullable', Rule::in(Companion::FSK_LEVELS)],
        ], attributes: ['name' => 'Name', 'maxFsk' => 'max. FSK']);

        Companion::create([
            'name' => trim($validated['name']),
            'max_fsk' => $this->toFsk($validated['maxFsk']),
            'sort' => (int) Companion::query()->max('sort') + 1,
        ]);

        $this->reset('name', 'maxFsk');
        unset($this->companions);
    }

    public function startEditing(int $companionId): void
    {
        $companion = Companion::findOrFail($companionId);

        $this->resetErrorBag();
        $this->editingId = $companion->id;
        $this->editName = $companion->name;
        $this->editMaxFsk = (string) $companion->max_fsk;
    }

    public function saveCompanion(): void
    {
        $companion = Companion::findOrFail($this->editingId);

        $validated = $this->validate([
            'editName' => ['required', 'string', 'max:50', Rule::unique('companions', 'name')->ignore($companion->id)],
            'editMaxFsk' => ['nullable', Rule::in(Companion::FSK_LEVELS)],
        ], attributes: ['editName' => 'Name', 'editMaxFsk' => 'max. FSK']);

        $companion->update([
            'name' => trim($validated['editName']),
            'max_fsk' => $this->toFsk($validated['editMaxFsk']),
        ]);

        $this->cancelEditing();
        unset($this->companions);
    }

    public function cancelEditing(): void
    {
        $this->reset('editingId', 'editName', 'editMaxFsk');
        $this->resetErrorBag();
    }

    public function deleteCompanion(int $companionId): void
    {
        Companion::findOrFail($companionId)->delete();

        if ($this->editingId === $companionId) {
            $this->cancelEditing();
        }

        unset($this->companions);
    }

    /**
     * Handler für wire:sort: verschiebt eine Begleitung an die neue (nullbasierte) Position.
     */
    public function moveCompanion(int|string $companionId, int $position): void
    {
        $ids = Companion::query()->ordered()->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $companionId = (int) $companionId;

        if (! in_array($companionId, $ids, true)) {
            return;
        }

        $ids = array_values(array_diff($ids, [$companionId]));
        array_splice($ids, max(0, min($position, count($ids))), 0, [$companionId]);

        DB::transaction(function () use ($ids): void {
            foreach ($ids as $index => $id) {
                Companion::whereKey($id)->update(['sort' => $index + 1]);
            }
        });

        unset($this->companions);
    }

    /**
     * Plex-Bibliothek sofort abgleichen statt erst in der Nacht.
     */
    public function syncPlex(PlexClient $plex): void
    {
        if ($plex->isConfigured()) {
            SyncPlexLibrary::dispatch();
            $this->plexSyncQueued = true;
        }
    }

    public function render(PlexClient $plex): View
    {
        $webhookSecret = (string) config('services.plex.webhook_secret');

        return view('livewire.settings', [
            'fskLevels' => Companion::FSK_LEVELS,
            'plexIsConfigured' => $plex->isConfigured(),
            'plexWebhookUrl' => $webhookSecret !== '' ? route('webhooks.plex', $webhookSecret) : null,
            'plexLastSync' => Cache::get(SyncPlexLibrary::LAST_SYNC_CACHE_KEY),
            'plexMovies' => Movie::query()->where('on_plex', true)->count(),
        ]);
    }

    private function toFsk(?string $value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }
}
