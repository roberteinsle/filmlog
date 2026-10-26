@php
    $selectClass = 'w-full rounded-lg border bg-stone-900 px-2.5 py-2 text-sm outline-none focus:border-amber-400';
    $filterIsActive = $this->filter->isActive();
    $movies = $this->movies;
    $statuses = $this->statuses;
    $options = $this->filterOptions;
@endphp

<div>
    <a href="{{ route('lists') }}" wire:navigate class="mb-3 inline-flex items-center gap-1 text-sm text-stone-500 hover:text-stone-200">
        <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6" /></svg>
        Alle Listen
    </a>

    <x-page-header :title="$list->name">
        {{ $totalCount }} {{ $totalCount === 1 ? 'Film' : 'Filme' }}@if ($list->is_watchlist) · Reihenfolge = was du zuerst sehen willst @endif
    </x-page-header>

    @if ($totalCount > 0)
        <div class="mb-5 grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-6" role="search" aria-label="Filter">
            <select wire:model.live="minOwnRating" aria-label="Eigene Bewertung" @class([$selectClass, 'border-amber-400 text-amber-200' => $this->filter->minOwnRating !== null, 'border-stone-700' => $this->filter->minOwnRating === null])>
                <option value="">Meine Bewertung</option>
                @foreach (range(10, 5) as $rating)
                    <option value="{{ $rating }}">ich ≥ {{ $rating }}</option>
                @endforeach
            </select>
            <select wire:model.live="minTmdbRating" aria-label="TMDB-Bewertung" @class([$selectClass, 'border-amber-400 text-amber-200' => $this->filter->minTmdbRating !== null, 'border-stone-700' => $this->filter->minTmdbRating === null])>
                <option value="">TMDB</option>
                @foreach (array_reverse($tmdbLevels) as $level)
                    <option value="{{ $level }}">TMDB ≥ {{ number_format($level, 1, ',', '') }}</option>
                @endforeach
            </select>
            <select wire:model.live="genreId" aria-label="Genre" @class([$selectClass, 'border-amber-400 text-amber-200' => $this->filter->genreId !== null, 'border-stone-700' => $this->filter->genreId === null])>
                <option value="">Genre</option>
                @foreach ($options['genres'] as $id => $genre)
                    <option value="{{ $id }}">{{ $genre }}</option>
                @endforeach
            </select>
            <select wire:model.live="decade" aria-label="Jahrzehnt" @class([$selectClass, 'border-amber-400 text-amber-200' => $this->filter->decade !== null, 'border-stone-700' => $this->filter->decade === null])>
                <option value="">Jahrzehnt</option>
                @foreach ($options['decades'] as $decadeOption)
                    <option value="{{ $decadeOption }}">{{ $decadeOption }}er</option>
                @endforeach
            </select>
            <select wire:model.live="maxRuntime" aria-label="Laufzeit" @class([$selectClass, 'border-amber-400 text-amber-200' => $this->filter->maxRuntime !== null, 'border-stone-700' => $this->filter->maxRuntime === null])>
                <option value="">Laufzeit</option>
                @foreach ($runtimeLimits as $minutes)
                    <option value="{{ $minutes }}">bis {{ $minutes }} Min.</option>
                @endforeach
            </select>
            <select wire:model.live="maxFsk" aria-label="Altersfreigabe" @class([$selectClass, 'border-amber-400 text-amber-200' => $this->filter->maxFsk !== null, 'border-stone-700' => $this->filter->maxFsk === null])>
                <option value="">FSK</option>
                @foreach ($fskLevels as $fsk)
                    <option value="{{ $fsk }}">bis FSK {{ $fsk }}</option>
                @endforeach
            </select>
        </div>

        @if ($filterIsActive)
            <div class="mb-3 flex items-center justify-between gap-3 text-sm text-stone-500">
                <p>{{ $movies->count() }} von {{ $totalCount }} · Sortieren geht nur ohne Filter.</p>
                <button type="button" wire:click="resetFilters" class="shrink-0 text-amber-300 hover:text-amber-200">Filter zurücksetzen</button>
            </div>
        @endif
    @endif

    @if ($totalCount === 0)
        <x-placeholder>
            Noch keine Filme auf dieser Liste.
            <a href="{{ route('search') }}" wire:navigate class="text-amber-300 underline-offset-2 hover:underline">Such einen Film</a>
            und füg ihn über „Zur Liste“ hinzu.
        </x-placeholder>
    @elseif ($movies->isEmpty())
        <x-placeholder>Kein Film passt zu diesen Filtern.</x-placeholder>
    @else
        <ol @unless ($filterIsActive) wire:sort="moveMovie" @endunless class="divide-y divide-stone-800/70">
            @foreach ($movies as $movie)
                @php($status = $statuses[$movie->tmdb_id])
                <li wire:key="item-{{ $movie->tmdb_id }}" @unless ($filterIsActive) wire:sort:item="{{ $movie->tmdb_id }}" @endunless class="flex items-center gap-3 bg-stone-950 py-3">
                    @unless ($filterIsActive)
                        <button type="button" wire:sort:handle class="cursor-grab touch-none text-stone-600 hover:text-stone-300 active:cursor-grabbing" aria-label="{{ $movie->title }} verschieben">
                            <svg class="size-5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><circle cx="9" cy="6" r="1.5" /><circle cx="15" cy="6" r="1.5" /><circle cx="9" cy="12" r="1.5" /><circle cx="15" cy="12" r="1.5" /><circle cx="9" cy="18" r="1.5" /><circle cx="15" cy="18" r="1.5" /></svg>
                        </button>
                    @endunless
                    <span class="w-6 shrink-0 text-right text-sm text-stone-600 tabular-nums">{{ $loop->iteration }}</span>

                    <a href="{{ route('movie', $movie->tmdb_id) }}" wire:navigate class="flex min-w-0 flex-1 items-center gap-3">
                        <x-poster :path="$movie->poster_path" :alt="$movie->title" size="w92" class="w-12 shrink-0" />
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <x-status-dot :status="$status" />
                                <p class="truncate font-medium">{{ $movie->title }}</p>
                            </div>
                            <p class="mt-0.5 text-sm text-stone-500">
                                {{ collect([
                                    $movie->year,
                                    \App\Livewire\MovieShow::formatRuntime($movie->runtime),
                                    $movie->fsk !== null ? 'FSK '.$movie->fsk : null,
                                    $movie->vote_average !== null && $movie->vote_count > 0 ? 'TMDB '.number_format($movie->vote_average, 1, ',', '') : null,
                                ])->filter(fn ($part) => $part !== null)->join(' · ') }}
                            </p>
                            @if ($status->lastRating !== null)
                                <p class="text-sm text-stone-400">ich: <span class="font-semibold text-amber-300">{{ $status->lastRating }}</span><span class="text-stone-600">/10</span></p>
                            @endif
                        </div>
                    </a>

                    <button type="button" wire:click="removeMovie({{ $movie->tmdb_id }})" wire:sort:ignore
                            wire:confirm="„{{ $movie->title }}“ von „{{ $list->name }}“ entfernen?"
                            class="shrink-0 p-1 text-stone-600 hover:text-red-400" aria-label="{{ $movie->title }} entfernen" title="Entfernen">
                        <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" /></svg>
                    </button>
                </li>
            @endforeach
        </ol>
    @endif
</div>
