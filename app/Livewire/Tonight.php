<?php

namespace App\Livewire;

use App\Actions\LogViewing;
use App\Actions\SuggestForTonight;
use App\Jobs\RefreshMovie;
use App\Livewire\Forms\ViewingForm;
use App\Models\Companion;
use App\Models\Movie;
use App\Models\MovieList;
use App\Models\StreamingSubscription;
use App\Services\MovieStatusResolver;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Heute-Abend-Assistent: Mit wem, wie viel Zeit, welche Stimmung → drei Vorschläge.
 * „Läuft gerade“ legt eine Sichtung von heute an und öffnet den Bewertungsdialog.
 */
#[Title('Heute Abend')]
class Tonight extends Component
{
    /**
     * Zeit-Auswahl: Wert in der URL → höchste Laufzeit (exklusiv) in Minuten.
     */
    public const array TIME_LIMITS = ['100' => 100, '130' => 130];

    /** @var list<int|string> */
    #[Url(as: 'mit')]
    public array $companionIds = [];

    #[Url(as: 'quelle', except: SuggestForTonight::SOURCE_WATCHLIST)]
    public string $source = SuggestForTonight::SOURCE_WATCHLIST;

    /** @var list<int|string> */
    #[Url(as: 'abo')]
    public array $providerIds = [];

    #[Url(as: 'zeit', except: '')]
    public string $time = '';

    #[Url(as: 'stimmung', except: '')]
    public string $mood = '';

    #[Locked]
    public int $seed = 0;

    /** @var list<int> */
    #[Locked]
    public array $shownTmdbIds = [];

    public ?string $notice = null;

    public ViewingForm $form;

    public bool $showViewingDialog = false;

    public bool $askRemoveFromWatchlist = false;

    #[Locked]
    public ?int $nowPlayingTmdbId = null;

    public function mount(): void
    {
        $this->seed = random_int(1, PHP_INT_MAX);
    }

    /**
     * @return Collection<int, Companion>
     */
    #[Computed]
    public function companions(): Collection
    {
        return Companion::query()->ordered()->get();
    }

    /**
     * Die gewählten Begleitungen (unbekannte IDs aus der URL fallen weg).
     *
     * @return Collection<int, Companion>
     */
    #[Computed]
    public function selectedCompanions(): Collection
    {
        $ids = array_map('intval', $this->companionIds);

        return $this->companions->filter(fn (Companion $companion): bool => in_array($companion->id, $ids, true))->values();
    }

    /**
     * Strengste Altersgrenze der Begleitungen; allein oder ohne Grenze = null.
     */
    #[Computed]
    public function maxFsk(): ?int
    {
        return $this->selectedCompanions->pluck('max_fsk')->filter(fn (?int $fsk): bool => $fsk !== null)->min();
    }

    /**
     * @return array{suggestions: SupportCollection<int, Movie>, candidates: int, remaining: int}
     */
    #[Computed]
    public function result(): array
    {
        return app(SuggestForTonight::class)->handle(
            maxFsk: $this->maxFsk,
            maxRuntime: self::TIME_LIMITS[$this->time] ?? null,
            genreIds: config("filmlog.moods.{$this->mood}.genres", []),
            excludedTmdbIds: $this->shownTmdbIds,
            seed: $this->seed,
            source: $this->source === SuggestForTonight::SOURCE_PLEX ? SuggestForTonight::SOURCE_PLEX : SuggestForTonight::SOURCE_WATCHLIST,
            providerIds: $this->selectedProviderIds,
        );
    }

    /**
     * @return Collection<int, StreamingSubscription>
     */
    #[Computed]
    public function subscriptions(): Collection
    {
        return StreamingSubscription::query()->orderBy('name')->get();
    }

    /**
     * Gewählte Abos für den Filter (nur bei Quelle Watchlist; unbekannte IDs aus der URL fallen weg).
     *
     * @return list<int>
     */
    #[Computed]
    public function selectedProviderIds(): array
    {
        if ($this->source !== SuggestForTonight::SOURCE_WATCHLIST) {
            return [];
        }

        return array_values(array_intersect($this->subscriptions->modelKeys(), array_map('intval', $this->providerIds)));
    }

    /**
     * Ungesehene Watchlist-Filme, deren Streaming-Anbieter noch nicht von TMDB geholt sind.
     */
    #[Computed]
    public function moviesWithoutProviders(): int
    {
        if ($this->selectedProviderIds === []) {
            return 0;
        }

        return $this->watchlistMoviesWithoutProviders()->count();
    }

    /**
     * Deine Abos, in denen die Vorschläge laufen (aus movies.provider_ids).
     *
     * @return array<int, Collection<int, StreamingSubscription>>
     */
    #[Computed]
    public function subscriptionsByMovie(): array
    {
        return $this->result['suggestions']->mapWithKeys(fn (Movie $movie): array => [
            $movie->tmdb_id => $this->subscriptions->filter(fn (StreamingSubscription $subscription): bool => in_array($subscription->provider_id, $movie->provider_ids ?? [], true))->values(),
        ])->all();
    }

    public function chooseAlone(): void
    {
        $this->companionIds = [];
        $this->criteriaChanged();
    }

    public function toggleCompanion(int $companionId): void
    {
        $ids = array_map('intval', $this->companionIds);

        $this->companionIds = in_array($companionId, $ids, true)
            ? array_values(array_diff($ids, [$companionId]))
            : [...$ids, $companionId];

        $this->criteriaChanged();
    }

    public function chooseSource(string $source): void
    {
        $this->source = $source === SuggestForTonight::SOURCE_PLEX ? SuggestForTonight::SOURCE_PLEX : SuggestForTonight::SOURCE_WATCHLIST;
        $this->criteriaChanged();
    }

    /**
     * Abo im Filter an-/abwählen; fehlen Anbieter-Infos, werden sie im Hintergrund von TMDB geholt.
     */
    public function toggleProvider(int $providerId): void
    {
        $ids = array_map('intval', $this->providerIds);

        $this->providerIds = in_array($providerId, $ids, true)
            ? array_values(array_diff($ids, [$providerId]))
            : [...$ids, $providerId];

        $this->criteriaChanged();

        if ($this->selectedProviderIds !== []) {
            $this->watchlistMoviesWithoutProviders()->pluck('movies.tmdb_id')->each(fn (int $tmdbId) => RefreshMovie::dispatch($tmdbId));
        }
    }

    public function chooseAllProviders(): void
    {
        $this->providerIds = [];
        $this->criteriaChanged();
    }

    public function chooseTime(string $time): void
    {
        $this->time = array_key_exists($time, self::TIME_LIMITS) ? $time : '';
        $this->criteriaChanged();
    }

    public function chooseMood(string $mood): void
    {
        $this->mood = array_key_exists($mood, config('filmlog.moods')) ? $mood : '';
        $this->criteriaChanged();
    }

    /**
     * „Andere Vorschläge“: die gezeigten ausblenden und neu würfeln; sind alle durch, geht es von vorn los.
     */
    public function otherSuggestions(): void
    {
        $this->shownTmdbIds = array_values(array_unique([...$this->shownTmdbIds, ...$this->result['suggestions']->pluck('tmdb_id')->all()]));
        $this->seed++;
        $this->notice = null;
        unset($this->result, $this->subscriptionsByMovie);

        if ($this->result['remaining'] === 0) {
            $this->shownTmdbIds = [];
            $this->notice = 'Das waren alle passenden Filme – es geht wieder von vorn los.';
            unset($this->result, $this->subscriptionsByMovie);
        }
    }

    public function nowPlaying(int $tmdbId, LogViewing $logViewing): void
    {
        $movie = Movie::findOrFail($tmdbId);

        $viewing = $logViewing->handle(
            tmdbId: $movie->tmdb_id,
            watchedAt: now(),
            companionIds: $this->selectedCompanions->modelKeys(),
        );

        $this->nowPlayingTmdbId = $movie->tmdb_id;
        $this->form->edit($viewing);
        $this->showViewingDialog = true;
        unset($this->result);
    }

    public function saveViewing(): void
    {
        if ($this->form->viewing !== null) {
            $this->form->update();
        }

        $this->closeViewingDialog();
    }

    public function closeViewingDialog(): void
    {
        $this->showViewingDialog = false;
        $this->form->viewing = null;

        if ($this->nowPlayingTmdbId !== null) {
            $this->askRemoveFromWatchlist = app(MovieStatusResolver::class)->for($this->nowPlayingTmdbId)->onWatchlist;
        }
    }

    public function removeFromWatchlist(): void
    {
        if ($this->nowPlayingTmdbId !== null) {
            MovieList::watchlist()->removeMovie($this->nowPlayingTmdbId);
        }

        $this->keepOnWatchlist();
    }

    public function keepOnWatchlist(): void
    {
        $this->askRemoveFromWatchlist = false;
        $this->nowPlayingTmdbId = null;
    }

    public function render(): View
    {
        return view('livewire.tonight', [
            'moods' => config('filmlog.moods'),
            'nowPlayingMovie' => $this->nowPlayingTmdbId !== null ? Movie::find($this->nowPlayingTmdbId) : null,
        ]);
    }

    private function criteriaChanged(): void
    {
        $this->shownTmdbIds = [];
        $this->notice = null;
        unset($this->selectedCompanions, $this->maxFsk, $this->selectedProviderIds, $this->moviesWithoutProviders, $this->result, $this->subscriptionsByMovie);
    }

    /**
     * @return Builder<Movie>
     */
    private function watchlistMoviesWithoutProviders(): Builder
    {
        return Movie::query()
            ->join('list_items', 'list_items.tmdb_id', '=', 'movies.tmdb_id')
            ->where('list_items.list_id', MovieList::watchlist()->id)
            ->whereNull('movies.provider_ids')
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('viewings')->whereColumn('viewings.tmdb_id', 'movies.tmdb_id'));
    }
}
