<?php

namespace App\Livewire;

use App\Actions\SyncMovie;
use App\Livewire\Forms\ViewingForm;
use App\Models\Companion;
use App\Models\Movie;
use App\Models\MovieList;
use App\Models\StreamingSubscription;
use App\Models\Viewing;
use App\Services\MovieStatusResolver;
use App\Services\Tmdb\MovieDetails;
use App\Services\Tmdb\MovieNotFoundException;
use App\Services\Tmdb\TmdbClient;
use App\Services\Tmdb\TmdbException;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Filmseite: Status-Banner ganz oben, darunter die Details live von TMDB.
 * „Gesehen“ öffnet den Sichtungs-Dialog; stand der Film auf der Watchlist, fragt Filmlog danach, ob er runter soll.
 */
class MovieShow extends Component
{
    #[Locked]
    public int $tmdbId;

    public ViewingForm $form;

    public bool $showViewingDialog = false;

    public bool $askRemoveFromWatchlist = false;

    public function mount(int $tmdbId): void
    {
        $this->tmdbId = $tmdbId;
    }

    /**
     * @return Collection<int, Companion>
     */
    #[Computed]
    public function companions(): Collection
    {
        return Companion::query()->ordered()->get();
    }

    public function openViewingDialog(): void
    {
        $this->form->startNew();
        $this->showViewingDialog = true;
    }

    public function closeViewingDialog(): void
    {
        $this->showViewingDialog = false;
        $this->form->viewing = null;
        unset($this->viewings);
    }

    /**
     * Alle Sichtungen dieses Films, neueste zuerst (für das aufklappbare Banner).
     *
     * @return Collection<int, Viewing>
     */
    #[Computed]
    public function viewings(): Collection
    {
        return Viewing::query()
            ->where('tmdb_id', $this->tmdbId)
            ->with('companions')
            ->orderByDesc('watched_at')
            ->orderByDesc('id')
            ->get();
    }

    public function editViewing(int $viewingId): void
    {
        $this->form->edit($this->ownViewing($viewingId));
        $this->showViewingDialog = true;
    }

    public function deleteViewing(int $viewingId): void
    {
        $this->ownViewing($viewingId)->delete();
        unset($this->viewings);
    }

    /**
     * Speichert eine neue Sichtung oder die bearbeitete aus dem Banner.
     */
    public function saveViewing(MovieStatusResolver $statuses): void
    {
        if ($this->form->viewing !== null) {
            $this->form->update();
            $this->closeViewingDialog();

            return;
        }

        Movie::findOrFail($this->tmdbId);

        $this->form->store($this->tmdbId);
        $this->closeViewingDialog();
        $this->askRemoveFromWatchlist = $statuses->for($this->tmdbId)->onWatchlist;
    }

    public function removeFromWatchlist(): void
    {
        MovieList::watchlist()->movies()->detach($this->tmdbId);
        $this->askRemoveFromWatchlist = false;
    }

    public function keepOnWatchlist(): void
    {
        $this->askRemoveFromWatchlist = false;
    }

    /**
     * Nur Sichtungen dieses Films lassen sich hier bearbeiten oder löschen.
     */
    private function ownViewing(int $viewingId): Viewing
    {
        return Viewing::query()->where('tmdb_id', $this->tmdbId)->findOrFail($viewingId);
    }

    /**
     * „Zur Liste“ hat etwas geändert: neu rendern, damit das Banner den Watchlist-Stand zeigt.
     */
    #[On('list-membership-changed')]
    public function refreshStatus(): void {}

    public function render(TmdbClient $tmdb, SyncMovie $syncMovie, MovieStatusResolver $statuses): View
    {
        $details = null;
        $error = null;

        try {
            $details = MovieDetails::fromTmdb($tmdb->movie($this->tmdbId), $tmdb->region());
            $movie = $syncMovie->handle($details);
        } catch (MovieNotFoundException) {
            abort(404);
        } catch (TmdbException $exception) {
            report($exception);
            $error = 'TMDB ist gerade nicht erreichbar. Bitte versuch es gleich noch einmal.';
            $movie = Movie::find($this->tmdbId);
        }

        return view('livewire.movie-show', [
            'details' => $details,
            'movie' => $movie,
            'status' => $statuses->for($this->tmdbId),
            'error' => $error,
            'subscribedProviderIds' => StreamingSubscription::providerIds(),
        ])->title($details->title ?? $movie->title ?? 'Film');
    }

    /**
     * „2 Std. 16 Min.“ bzw. „95 Min.“
     */
    public static function formatRuntime(?int $minutes): ?string
    {
        if ($minutes === null) {
            return null;
        }

        return $minutes >= 60
            ? intdiv($minutes, 60).' Std.'.($minutes % 60 > 0 ? ' '.($minutes % 60).' Min.' : '')
            : $minutes.' Min.';
    }
}
