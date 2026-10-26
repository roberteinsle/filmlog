@php
    $result = $this->result;
    $selectedIds = $this->selectedCompanions->modelKeys();
@endphp

<div>
    <x-page-header title="Heute Abend">Mit wem, wie viel Zeit, welche Stimmung – daraus werden drei Vorschläge.</x-page-header>

    <div class="space-y-5">
        <fieldset>
            <legend class="mb-2 text-sm font-medium text-stone-400">Woher?</legend>
            <div class="inline-flex rounded-full border border-stone-700 p-0.5" role="radiogroup">
                @foreach (['watchlist' => 'Watchlist', 'plex' => 'Plex-Bibliothek'] as $sourceKey => $sourceLabel)
                    <button type="button" role="radio" aria-checked="{{ $source === $sourceKey ? 'true' : 'false' }}" wire:click="chooseSource('{{ $sourceKey }}')"
                            @class([
                                'inline-flex items-center gap-1.5 rounded-full px-3.5 py-1.5 text-sm transition',
                                'bg-amber-400 font-medium text-stone-950' => $source === $sourceKey,
                                'text-stone-300 hover:text-stone-100' => $source !== $sourceKey,
                            ])>
                        @if ($sourceKey === 'plex')<x-plex-icon class="size-4" />@endif
                        {{ $sourceLabel }}
                    </button>
                @endforeach
            </div>
        </fieldset>

        <fieldset>
            <legend class="mb-2 text-sm font-medium text-stone-400">Mit wem?</legend>
            <div class="flex flex-wrap gap-2">
                <x-choice-chip wire:click="chooseAlone" :active="$selectedIds === []">Allein</x-choice-chip>
                @foreach ($this->companions as $companion)
                    <x-choice-chip wire:key="companion-{{ $companion->id }}" wire:click="toggleCompanion({{ $companion->id }})" :active="in_array($companion->id, $selectedIds, true)">
                        {{ $companion->name }}@if ($companion->max_fsk !== null)<span class="ml-1 text-xs opacity-60">FSK {{ $companion->max_fsk }}</span>@endif
                    </x-choice-chip>
                @endforeach
                @if ($this->companions->isEmpty())
                    <a href="{{ route('settings') }}" wire:navigate class="self-center text-sm text-stone-500 underline-offset-2 hover:text-stone-300 hover:underline">Begleitungen anlegen</a>
                @endif
            </div>
        </fieldset>

        <fieldset>
            <legend class="mb-2 text-sm font-medium text-stone-400">Wie viel Zeit?</legend>
            <div class="flex flex-wrap gap-2">
                <x-choice-chip wire:click="chooseTime('100')" :active="$time === '100'">unter 100 Min.</x-choice-chip>
                <x-choice-chip wire:click="chooseTime('130')" :active="$time === '130'">unter 130 Min.</x-choice-chip>
                <x-choice-chip wire:click="chooseTime('')" :active="$time === ''">egal</x-choice-chip>
            </div>
        </fieldset>

        <fieldset>
            <legend class="mb-2 text-sm font-medium text-stone-400">Worauf hast du Lust?</legend>
            <div class="flex flex-wrap gap-2">
                @foreach ($moods as $moodKey => $moodOption)
                    <x-choice-chip wire:key="mood-{{ $moodKey }}" wire:click="chooseMood('{{ $moodKey }}')" :active="$mood === $moodKey">{{ $moodOption['label'] }}</x-choice-chip>
                @endforeach
                <x-choice-chip wire:click="chooseMood('')" :active="$mood === ''">egal</x-choice-chip>
            </div>
        </fieldset>
    </div>

    <section class="mt-8" aria-live="polite">
        <div class="mb-3 flex items-baseline justify-between gap-3">
            <h2 class="text-sm font-medium tracking-wide text-stone-500 uppercase">Vorschläge</h2>
            @if ($result['candidates'] > 0)
                <p class="text-xs text-stone-500">
                    aus {{ $result['candidates'] }} passenden {{ $result['candidates'] === 1 ? 'Film' : 'Filmen' }}@if ($this->maxFsk !== null) · bis FSK {{ $this->maxFsk }}@endif
                </p>
            @endif
        </div>

        @if ($notice)
            <p class="mb-3 text-sm text-amber-300">{{ $notice }}</p>
        @endif

        @if ($result['suggestions']->isEmpty())
            <x-placeholder>
                Kein ungesehener Film passt dazu.
                @if ($source === 'plex')
                    Lockere die Auswahl oder gleich deine Plex-Bibliothek in den <a href="{{ route('settings') }}" wire:navigate class="text-amber-300 underline-offset-2 hover:underline">Einstellungen</a> ab.
                @else
                    Lockere die Auswahl, füll deine <a href="{{ route('lists') }}" wire:navigate class="text-amber-300 underline-offset-2 hover:underline">Watchlist</a> oder schalte auf Plex-Bibliothek um.
                @endif
            </x-placeholder>
        @else
            <ul class="grid gap-4 sm:grid-cols-3" wire:loading.class="opacity-60">
                @foreach ($result['suggestions'] as $movie)
                    <li wire:key="suggestion-{{ $movie->tmdb_id }}" class="flex gap-4 rounded-xl border border-stone-800 bg-stone-900/40 p-3 sm:flex-col">
                        <a href="{{ route('movie', $movie->tmdb_id) }}" wire:navigate class="shrink-0">
                            <x-poster :path="$movie->poster_path" :alt="$movie->title" size="w342" class="w-24 sm:w-full" />
                        </a>
                        <div class="flex min-w-0 flex-1 flex-col">
                            <a href="{{ route('movie', $movie->tmdb_id) }}" wire:navigate class="font-medium hover:text-amber-300">{{ $movie->title }}</a>
                            <p class="mt-0.5 text-sm text-stone-500">
                                {{ collect([
                                    $movie->year,
                                    \App\Livewire\MovieShow::formatRuntime($movie->runtime),
                                    $movie->fsk !== null ? 'FSK '.$movie->fsk : null,
                                    $movie->vote_average !== null && $movie->vote_count > 0 ? 'TMDB '.number_format($movie->vote_average, 1, ',', '') : null,
                                ])->filter(fn ($part) => $part !== null)->join(' · ') }}
                            </p>
                            <p class="mt-1 flex flex-wrap gap-1 text-xs">
                                @if ($movie->watchlist_rank !== null)
                                    <span class="rounded bg-stone-800 px-1.5 py-0.5 text-stone-300">Watchlist</span>
                                @endif
                                @foreach ($this->subscriptionsByMovie[$movie->tmdb_id] ?? [] as $provider)
                                    <span class="inline-flex items-center gap-1 rounded bg-emerald-500/15 py-0.5 pr-1.5 pl-0.5 text-emerald-200">
                                        <x-provider-logo :path="$provider['logo_path']" class="size-4" />
                                        {{ $provider['name'] }}
                                    </span>
                                @endforeach
                                @if ($movie->on_plex)
                                    <span class="inline-flex items-center gap-1 rounded bg-amber-400/15 py-0.5 pr-1.5 pl-0.5 text-amber-200">
                                        <x-plex-icon class="size-4" />
                                        Plex
                                    </span>
                                @endif
                            </p>
                            <div class="mt-auto pt-3">
                                <button type="button" wire:click="nowPlaying({{ $movie->tmdb_id }})" class="w-full rounded-lg bg-amber-400 px-3 py-2 text-sm font-semibold text-stone-950 transition hover:bg-amber-300">Läuft gerade</button>
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>

            <div class="mt-4 text-center">
                <button type="button" wire:click="otherSuggestions" class="rounded-lg border border-stone-700 px-4 py-2 text-sm text-stone-300 hover:border-stone-500">Andere Vorschläge</button>
            </div>
        @endif
    </section>

    @if ($showViewingDialog && $form->viewing && $nowPlayingMovie)
        <x-modal :title="'„'.$nowPlayingMovie->title.'“ läuft'" close="closeViewingDialog">
            <p class="-mt-2 mb-4 text-sm text-stone-400">Die Sichtung von heute ist eingetragen. Bewerten kannst du jetzt oder später im Tagebuch.</p>
            <x-viewing-form :companions="$this->companions" submit="saveViewing" cancel="closeViewingDialog" submit-label="Speichern" />
        </x-modal>
    @endif

    @if ($askRemoveFromWatchlist)
        <x-modal title="Von der Watchlist nehmen?" close="keepOnWatchlist">
            <p class="text-sm text-stone-400">Der Film steht noch auf deiner Watchlist.</p>
            <div class="mt-5 flex justify-end gap-2">
                <button type="button" wire:click="keepOnWatchlist" class="rounded-lg px-4 py-2 text-sm text-stone-400 hover:text-stone-100">Drauflassen</button>
                <button type="button" wire:click="removeFromWatchlist" class="rounded-lg bg-amber-400 px-4 py-2 text-sm font-semibold text-stone-950 transition hover:bg-amber-300">Entfernen</button>
            </div>
        </x-modal>
    @endif
</div>
