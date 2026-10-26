@php
    $stats = $this->stats;
    $chipClass = 'rounded-full border px-3 py-1 text-sm transition';
    $formatRating = fn (?float $rating): string => $rating !== null ? number_format($rating, 1, ',', '') : '–';
@endphp

<div @if ($isLoadingCollections) wire:poll.5s @endif>
    <x-page-header title="Rückblick">Monats- und Jahresstatistik, „Gegen den Strom“ und „Lücken“.</x-page-header>

    @if ($stats === null)
        <x-placeholder>
            Noch keine Sichtungen.
            <a href="{{ route('search') }}" wire:navigate class="text-amber-300 underline-offset-2 hover:underline">Such einen Film</a>
            oder übernimm deinen Verlauf per <a href="{{ route('import') }}" wire:navigate class="text-amber-300 underline-offset-2 hover:underline">Trakt-Import</a>.
        </x-placeholder>
    @else
        @php
            $summary = $stats->summary();
            $perMonth = $stats->viewingsPerMonth();
            $directors = $stats->favoriteDirectors();
            $genres = $stats->topGenres();
            $againstTheGrain = $stats->againstTheGrain();
            $periodLabel = $this->selectedMonth !== null ? $stats->start->translatedFormat('F Y') : (string) $stats->year;
        @endphp

        <nav class="space-y-3" aria-label="Zeitraum">
            <div class="flex flex-wrap gap-2">
                @foreach ($this->years as $yearOption)
                    <button type="button" wire:key="year-{{ $yearOption }}" wire:click="chooseYear({{ $yearOption }})" aria-pressed="{{ $yearOption === $this->selectedYear ? 'true' : 'false' }}"
                            @class([$chipClass, 'border-amber-400 bg-amber-400/15 text-amber-200' => $yearOption === $this->selectedYear, 'border-stone-700 text-stone-300 hover:border-stone-500' => $yearOption !== $this->selectedYear])>{{ $yearOption }}</button>
                @endforeach
            </div>
            <div class="grid grid-cols-6 gap-1.5 sm:grid-cols-12">
                @foreach ($perMonth as $monthNumber => $monthViewings)
                    <button type="button" wire:key="month-{{ $monthNumber }}" wire:click="chooseMonth({{ $monthNumber }})" @disabled($monthViewings === 0)
                            aria-pressed="{{ $monthNumber === $this->selectedMonth ? 'true' : 'false' }}"
                            @class([
                                'flex flex-col items-center rounded-lg border px-1 py-1.5 text-xs transition disabled:opacity-40',
                                'border-amber-400 bg-amber-400/15 text-amber-200' => $monthNumber === $this->selectedMonth,
                                'border-stone-800 text-stone-400 enabled:hover:border-stone-600' => $monthNumber !== $this->selectedMonth,
                            ])>
                        <span>{{ $stats->start->setMonth($monthNumber)->translatedFormat('M') }}</span>
                        <span class="font-semibold text-stone-200 tabular-nums">{{ $monthViewings }}</span>
                    </button>
                @endforeach
            </div>
        </nav>

        <section class="mt-6">
            <h2 class="mb-3 text-lg font-semibold tracking-tight">{{ $periodLabel }}</h2>
            <dl class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                @foreach ([
                    'Sichtungen' => $summary['viewings'],
                    'Filme' => $summary['movies'],
                    'Stunden' => number_format($summary['minutes'] / 60, $summary['minutes'] < 600 ? 1 : 0, ',', '.'),
                    'Ø Bewertung' => $formatRating($summary['average_rating']),
                ] as $label => $value)
                    <div class="rounded-xl border border-stone-800 px-4 py-3">
                        <dt class="text-xs text-stone-500">{{ $label }}</dt>
                        <dd class="mt-1 text-2xl font-semibold tabular-nums">{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>
        </section>

        @if ($summary['viewings'] > 0)
            <div class="mt-8 grid gap-8 md:grid-cols-2">
                <section>
                    <h3 class="mb-2 text-sm font-medium tracking-wide text-stone-500 uppercase">Top-Genres</h3>
                    <ol class="space-y-1.5 text-sm">
                        @foreach ($genres as $genre)
                            <li class="flex items-baseline justify-between gap-3">
                                <span>{{ $genre['name'] }}</span>
                                <span class="text-stone-500 tabular-nums">{{ $genre['viewings'] }}×</span>
                            </li>
                        @endforeach
                    </ol>
                </section>

                <section>
                    <h3 class="mb-2 text-sm font-medium tracking-wide text-stone-500 uppercase">Lieblingsregie</h3>
                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <p class="mb-1 text-xs text-stone-500">am häufigsten</p>
                            <ol class="space-y-1.5">
                                @foreach ($directors['by_count'] as $director)
                                    <li class="flex items-baseline justify-between gap-2">
                                        <span class="truncate">{{ $director['name'] }}</span>
                                        <span class="shrink-0 text-stone-500 tabular-nums">{{ $director['viewings'] }}×</span>
                                    </li>
                                @endforeach
                            </ol>
                        </div>
                        <div>
                            <p class="mb-1 text-xs text-stone-500">am besten bewertet</p>
                            <ol class="space-y-1.5">
                                @forelse ($directors['by_rating'] as $director)
                                    <li class="flex items-baseline justify-between gap-2">
                                        <span class="truncate">{{ $director['name'] }}</span>
                                        <span class="shrink-0 text-amber-300 tabular-nums">Ø {{ $formatRating($director['average_rating']) }}</span>
                                    </li>
                                @empty
                                    <li class="text-xs text-stone-600">ab 2 bewerteten Filmen</li>
                                @endforelse
                            </ol>
                        </div>
                    </div>
                </section>
            </div>

            <section class="mt-8">
                <h3 class="mb-1 text-sm font-medium tracking-wide text-stone-500 uppercase">Gegen den Strom</h3>
                <p class="mb-3 text-xs text-stone-500">Wo deine Bewertung am weitesten von TMDB abweicht (nur Filme ab {{ config('filmlog.tmdb_min_votes') }} TMDB-Stimmen).</p>
                @if ($againstTheGrain->isEmpty())
                    <p class="text-sm text-stone-500">Keine bewerteten Filme in diesem Zeitraum.</p>
                @else
                    <ol class="divide-y divide-stone-800/70">
                        @foreach ($againstTheGrain as $movie)
                            <li wire:key="grain-{{ $movie['tmdb_id'] }}" class="flex items-center gap-3 py-2 text-sm">
                                <a href="{{ route('movie', $movie['tmdb_id']) }}" wire:navigate class="min-w-0 flex-1 truncate hover:text-amber-300">
                                    {{ $movie['title'] }} @if ($movie['year'])<span class="text-stone-500">({{ $movie['year'] }})</span>@endif
                                </a>
                                <span class="shrink-0 text-stone-400 tabular-nums">ich {{ $movie['rating'] }} · TMDB {{ number_format($movie['tmdb_rating'], 1, ',', '') }}</span>
                                <span @class(['w-14 shrink-0 text-right font-semibold tabular-nums', 'text-emerald-400' => $movie['difference'] > 0, 'text-red-400' => $movie['difference'] <= 0])>
                                    {{ ($movie['difference'] > 0 ? '+' : '').number_format($movie['difference'], 1, ',', '') }}
                                </span>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </section>
        @endif

        <section class="mt-10">
            <h2 class="mb-1 text-lg font-semibold tracking-tight">Lücken</h2>
            <p class="mb-3 text-sm text-stone-400">Filmreihen, von denen du schon Teile gesehen hast, aber noch nicht alle erschienenen.</p>

            @if ($pendingCollections > 0)
                <div class="mb-4 flex flex-wrap items-center gap-3 rounded-lg border border-stone-800 px-3 py-2 text-sm text-stone-400">
                    @if ($isLoadingCollections)
                        <span>Filmreihen werden geladen … noch {{ $pendingCollections }}.</span>
                    @else
                        <span>{{ $pendingCollections }} {{ $pendingCollections === 1 ? 'Filmreihe ist' : 'Filmreihen sind' }} noch nicht geladen (passiert sonst nachts).</span>
                        <button type="button" wire:click="loadCollections" class="text-amber-300 hover:text-amber-200">Jetzt laden</button>
                    @endif
                </div>
            @endif

            @if ($gaps->isEmpty())
                <p class="text-sm text-stone-500">Keine Lücken gefunden.</p>
            @else
                <ul class="space-y-5">
                    @foreach ($gaps as $gap)
                        <li wire:key="gap-{{ $gap['collection']->id }}">
                            <p class="flex items-baseline justify-between gap-3">
                                <span class="font-medium">{{ $gap['collection']->name }}</span>
                                <span class="shrink-0 text-sm text-stone-500">{{ $gap['seen'] }} von {{ $gap['released'] }} gesehen</span>
                            </p>
                            <ul class="mt-2 flex gap-3 overflow-x-auto pb-1">
                                @foreach ($gap['missing'] as $part)
                                    <li wire:key="part-{{ $part['id'] }}" class="w-20 shrink-0">
                                        <a href="{{ route('movie', $part['id']) }}" wire:navigate class="group block">
                                            <x-poster :path="$part['poster_path']" :alt="$part['title']" size="w154" class="w-20" />
                                            <span class="mt-1 line-clamp-2 text-xs text-stone-400 group-hover:text-amber-300">{{ $part['title'] }}</span>
                                            <span class="text-xs text-stone-600">{{ substr((string) $part['release_date'], 0, 4) }}</span>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    @endif
</div>
