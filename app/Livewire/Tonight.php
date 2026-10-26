<?php

namespace App\Livewire;

use App\Actions\LogViewing;
use App\Actions\SuggestForTonight;
use App\Livewire\Forms\ViewingForm;
use App\Models\Companion;
use App\Models\Movie;
use App\Models\MovieList;
use App\Services\MovieStatusResolver;
use Illuminate\Contracts\View\View;
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
        );
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
        unset($this->result);

        if ($this->result['remaining'] === 0) {
            $this->shownTmdbIds = [];
            $this->notice = 'Das waren alle passenden Filme – es geht wieder von vorn los.';
            unset($this->result);
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
        unset($this->selectedCompanions, $this->maxFsk, $this->result);
    }
}
