@php
    $entries = $this->entries;
    $statuses = $this->statuses;
    $subscriptions = $this->subscriptions;
    $formatVisitors = fn (?int $visitors): string => $visitors === null ? '' : ($visitors >= 1_000_000
        ? number_format($visitors / 1_000_000, 1, ',', '.').' Mio. Besucher'
        : number_format($visitors, 0, ',', '.').' Besucher');
@endphp

<div @if ($isLoading) wire:poll.3s @endif>
    <x-page-header title="Kinocharts">Die erfolgreichsten Filme in deutschen Kinos, nach Jahr.</x-page-header>

    <div class="mb-6 flex items-center gap-3">
        <label for="chart-year" class="text-sm text-stone-400">Jahr</label>
        <select id="chart-year" wire:change="chooseYear($event.target.value)"
                class="rounded-lg border border-stone-700 bg-stone-900 px-3 py-2 text-sm outline-none focus:border-amber-400">
            @foreach ($this->years as $yearOption)
                <option value="{{ $yearOption }}" @selected($yearOption === $this->selectedYear)>{{ $yearOption }}</option>
            @endforeach
        </select>
        @if ($isLoading)
            <span class="text-xs text-amber-300">
                @if ($entries->isEmpty())
                    wird geladen …
                @else
                    ordne Filme zu … noch {{ $unresolvedCount }}
                @endif
            </span>
        @endif
    </div>

    @if ($chart->status === \App\Models\CinemaChartYear::FAILED && $entries->isEmpty())
        <x-placeholder>{{ $chart->error ?? 'Die Kinocharts konnten nicht geladen werden.' }}</x-placeholder>
    @elseif ($entries->isEmpty())
        <x-placeholder>Die Kinocharts {{ $this->selectedYear }} werden von InsideKino geladen …</x-placeholder>
    @else
        <ol class="divide-y divide-stone-800/70">
            @foreach ($entries as $entry)
                @php($movie = $entry->movie)
                <li wire:key="chart-{{ $entry->id }}" class="flex items-center gap-3 py-3">
                    <span class="w-7 shrink-0 text-right text-sm font-semibold text-stone-500 tabular-nums">{{ $entry->rank }}</span>

                    @if ($movie)
                        <a href="{{ route('movie', $movie->tmdb_id) }}" wire:navigate class="shrink-0">
                            <x-poster :path="$movie->poster_path" :alt="$movie->title" size="w92" class="w-12" />
                        </a>
                    @else
                        <div class="aspect-[2/3] w-12 shrink-0 rounded-md bg-stone-800"></div>
                    @endif

                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2">
                            @if ($movie)
                                <x-status-dot :status="$statuses[$movie->tmdb_id]" />
                                <a href="{{ route('movie', $movie->tmdb_id) }}" wire:navigate class="truncate font-medium hover:text-amber-300">{{ $movie->title }}</a>
                            @else
                                <span class="truncate font-medium text-stone-300">{{ $entry->title }}</span>
                            @endif
                        </div>
                        <p class="mt-0.5 text-xs text-stone-500">
                            {{ collect([
                                $movie?->year,
                                $movie?->vote_average !== null && $movie?->vote_count > 0 ? 'TMDB '.number_format($movie->vote_average, 1, ',', '') : null,
                                $formatVisitors($entry->visitors),
                            ])->filter()->join(' · ') }}
                            @if (! $movie && $entry->resolved_at)
                                · <span class="text-stone-600">bei TMDB nicht gefunden</span>
                            @endif
                        </p>
                        @if ($movie)
                            @php($yourSubscriptions = $subscriptions->filter(fn ($subscription) => in_array($subscription->provider_id, $movie->provider_ids ?? [], true)))
                            @if ($movie->on_plex || $yourSubscriptions->isNotEmpty())
                                <p class="mt-1 flex flex-wrap gap-1 text-xs">
                                    @if ($movie->on_plex)
                                        <span class="inline-flex items-center gap-1 rounded bg-amber-400/15 py-0.5 pr-1.5 pl-0.5 text-amber-200"><x-plex-icon class="size-4" /> Plex</span>
                                    @endif
                                    @foreach ($yourSubscriptions as $subscription)
                                        <span wire:key="chart-{{ $entry->id }}-provider-{{ $subscription->provider_id }}" class="inline-flex items-center gap-1 rounded bg-sky-500/15 py-0.5 pr-1.5 pl-0.5 text-sky-200">
                                            <x-provider-logo :path="$subscription->logo_path" class="size-4" />
                                            {{ $subscription->name }}
                                        </span>
                                    @endforeach
                                </p>
                            @endif
                        @endif
                    </div>
                </li>
            @endforeach
        </ol>

        <p class="mt-6 text-xs text-stone-600">
            Quelle: <a href="{{ $sourceUrl }}" target="_blank" rel="noopener noreferrer" class="underline-offset-2 hover:text-stone-400 hover:underline">InsideKino – Die erfolgreichsten Filme in Deutschland {{ $this->selectedYear }}</a>
        </p>
    @endif
</div>
