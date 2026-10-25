<?php

namespace App\Livewire;

use App\Actions\SyncMovie;
use App\Models\Movie;
use App\Services\MovieStatusResolver;
use App\Services\Tmdb\MovieDetails;
use App\Services\Tmdb\MovieNotFoundException;
use App\Services\Tmdb\TmdbClient;
use App\Services\Tmdb\TmdbException;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Filmseite: Status-Banner ganz oben, darunter die Details live von TMDB.
 */
class MovieShow extends Component
{
    #[Locked]
    public int $tmdbId;

    public function mount(int $tmdbId): void
    {
        $this->tmdbId = $tmdbId;
    }

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
