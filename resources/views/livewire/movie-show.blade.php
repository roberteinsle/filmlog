<div class="space-y-6">
    <div class="space-y-3">
        <x-status-banner :status="$status">
            @if ($status->state === \App\Services\WatchState::Seen)
                <x-slot:details>
                    <ol class="divide-y divide-red-200/10">
                        @foreach ($this->viewings as $viewing)
                            @php($watchedAt = $viewing->watched_at->timezone(config('app.timezone')))
                            <li wire:key="banner-viewing-{{ $viewing->id }}" class="flex items-start gap-3 py-2">
                                <div class="min-w-0 flex-1">
                                    <p>
                                        <span class="font-medium">{{ $watchedAt->translatedFormat('D, d.m.Y') }}</span>
                                        @if ($viewing->rating !== null)
                                            · <span class="font-semibold">{{ $viewing->rating }}</span><span class="opacity-60">/10</span>
                                        @endif
                                        · {{ $viewing->companions->isEmpty() ? 'allein' : 'mit '.$viewing->companions->pluck('name')->join(', ', ' und ') }}
                                        @if ($viewing->source !== 'manual')
                                            <span class="ml-1 rounded border border-red-200/20 px-1 text-xs opacity-70">{{ ucfirst($viewing->source) }}</span>
                                        @endif
                                    </p>
                                    @if ($viewing->note)
                                        <p class="italic opacity-80">„{{ $viewing->note }}“</p>
                                    @endif
                                </div>
                                <div class="flex shrink-0 gap-3 text-xs">
                                    <button type="button" wire:click="editViewing({{ $viewing->id }})" class="opacity-70 hover:opacity-100">Bearbeiten</button>
                                    <button type="button" wire:click="deleteViewing({{ $viewing->id }})" wire:confirm="Sichtung vom {{ $watchedAt->format('d.m.Y') }} löschen?" class="opacity-70 hover:text-red-300 hover:opacity-100">Löschen</button>
                                </div>
                            </li>
                        @endforeach
                    </ol>
                </x-slot:details>
            @endif
        </x-status-banner>

        @if ($movie)
            <div class="flex justify-end gap-2">
                <livewire:add-to-list :tmdb-id="$tmdbId" :key="'add-to-list-'.$tmdbId" />
                <button type="button" wire:click="openViewingDialog" class="inline-flex items-center gap-2 rounded-lg bg-amber-400 px-4 py-2 text-sm font-semibold text-stone-950 transition hover:bg-amber-300">
                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12l5 5L20 7" /></svg>
                    {{ $status->state === \App\Services\WatchState::Seen ? 'Wieder gesehen' : 'Gesehen' }}
                </button>
            </div>
        @endif
    </div>

    @if ($showViewingDialog)
        <x-modal :title="$form->viewing ? 'Sichtung vom '.$form->viewing->watched_at->timezone(config('app.timezone'))->format('d.m.Y').' bearbeiten' : ($details->title ?? $movie->title).' gesehen'" close="closeViewingDialog">
            <x-viewing-form :companions="$this->companions" submit="saveViewing" cancel="closeViewingDialog" />
        </x-modal>
    @endif

    @if ($askRemoveFromWatchlist)
        <x-modal title="Von der Watchlist nehmen?" close="keepOnWatchlist">
            <p class="text-sm text-stone-400">Die Sichtung ist gespeichert. Der Film steht noch auf deiner Watchlist.</p>
            <div class="mt-5 flex justify-end gap-2">
                <button type="button" wire:click="keepOnWatchlist" class="rounded-lg px-4 py-2 text-sm text-stone-400 hover:text-stone-100">Drauflassen</button>
                <button type="button" wire:click="removeFromWatchlist" class="rounded-lg bg-amber-400 px-4 py-2 text-sm font-semibold text-stone-950 transition hover:bg-amber-300">Entfernen</button>
            </div>
        </x-modal>
    @endif

    @if ($error)
        <x-placeholder>{{ $error }}</x-placeholder>
    @endif

    @if ($details)
        <section class="flex flex-col gap-6 sm:flex-row">
            <x-poster :path="$details->posterPath" :alt="$details->title" size="w342" class="mx-auto w-44 shrink-0 shadow-lg shadow-black/40 sm:mx-0 sm:w-52" />

            <div class="min-w-0 flex-1 space-y-4">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">
                        {{ $details->title }}
                        @if ($details->year)
                            <span class="font-normal text-stone-500">({{ $details->year }})</span>
                        @endif
                    </h1>
                    @if ($details->originalTitle)
                        <p class="mt-1 text-sm text-stone-500">Originaltitel: {{ $details->originalTitle }}</p>
                    @endif
                    @if ($details->tagline)
                        <p class="mt-2 text-stone-400 italic">{{ $details->tagline }}</p>
                    @endif
                </div>

                <dl class="flex flex-wrap gap-2 text-sm">
                    @if ($runtime = \App\Livewire\MovieShow::formatRuntime($details->runtime))
                        <div class="rounded-md bg-stone-800 px-2.5 py-1"><dt class="sr-only">Laufzeit</dt><dd>{{ $runtime }}</dd></div>
                    @endif
                    <div class="rounded-md bg-stone-800 px-2.5 py-1">
                        <dt class="sr-only">Altersfreigabe</dt>
                        <dd>{{ $details->fsk !== null ? 'FSK '.$details->fsk : 'FSK unbekannt' }}</dd>
                    </div>
                    @if ($details->voteAverage !== null && $details->voteCount > 0)
                        <div class="rounded-md bg-stone-800 px-2.5 py-1">
                            <dt class="sr-only">TMDB-Bewertung</dt>
                            <dd>TMDB {{ number_format($details->voteAverage, 1, ',', '') }} <span class="text-stone-500">({{ number_format($details->voteCount, 0, ',', '.') }} Stimmen)</span></dd>
                        </div>
                    @endif
                    @foreach ($details->genres as $genre)
                        <div class="rounded-md border border-stone-700 px-2.5 py-1 text-stone-300"><dt class="sr-only">Genre</dt><dd>{{ $genre }}</dd></div>
                    @endforeach
                </dl>

                @if ($details->directors !== [])
                    <p class="text-sm text-stone-400">
                        Regie: <span class="text-stone-200">{{ collect($details->directors)->pluck('name')->join(', ') }}</span>
                    </p>
                @endif

                <div class="space-y-2 text-sm">
                    @php($yourProviders = collect($details->streamingProviders)->whereIn('id', $subscribedProviderIds)->values())
                    @if ($yourProviders->isNotEmpty() || $movie?->on_plex || $subscribedProviderIds !== [])
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-stone-400">Für dich</span>
                            @foreach ($yourProviders as $provider)
                                <span class="inline-flex items-center gap-1.5 rounded-md bg-emerald-500/15 py-1 pr-2.5 pl-1 text-emerald-200">
                                    <x-provider-logo :path="$provider['logo_path']" />
                                    {{ $provider['name'] }} <span class="text-xs text-emerald-300/70">im Abo</span>
                                </span>
                            @endforeach
                            @if ($movie?->on_plex)
                                <span class="inline-flex items-center gap-1.5 rounded-md bg-amber-400/15 py-1 pr-2.5 pl-1 font-medium text-amber-200">
                                    <x-plex-icon />
                                    Auf Plex vorhanden
                                </span>
                            @endif
                            @if ($yourProviders->isEmpty() && ! $movie?->on_plex)
                                <span class="text-stone-500">nicht in deinen Abos und nicht auf Plex</span>
                            @endif
                        </div>
                    @endif

                    @if ($details->streamingProviders !== [])
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-stone-400">Streamt bei</span>
                            @foreach ($details->streamingProviders as $provider)
                                <span @class([
                                    'inline-flex items-center gap-1.5 rounded-md bg-stone-800 py-1 pr-2.5 pl-1',
                                    'ring-1 ring-emerald-500/60' => in_array($provider['id'], $subscribedProviderIds, true),
                                ])>
                                    <x-provider-logo :path="$provider['logo_path']" />
                                    {{ $provider['name'] }}
                                </span>
                            @endforeach
                        </div>
                    @elseif (! $movie?->on_plex)
                        <p class="text-stone-500">Aktuell bei keinem Streaming-Abo in Deutschland.</p>
                    @endif

                    @if ($details->providersLink)
                        <a href="{{ $details->providersLink }}" target="_blank" rel="noopener noreferrer" class="inline-block text-xs text-stone-500 underline-offset-2 hover:text-stone-300 hover:underline">
                            Alle Angebote (Leihen, Kaufen) bei TMDB / JustWatch
                        </a>
                    @endif
                </div>
            </div>
        </section>

        @if ($details->overview)
            <section>
                <h2 class="mb-2 text-sm font-medium tracking-wide text-stone-500 uppercase">Inhalt</h2>
                <p class="leading-relaxed text-stone-300">{{ $details->overview }}</p>
            </section>
        @endif

        @if ($details->cast !== [])
            <section>
                <h2 class="mb-3 text-sm font-medium tracking-wide text-stone-500 uppercase">Besetzung</h2>
                <ul class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
                    @foreach ($details->cast as $person)
                        <li class="flex items-center gap-3">
                            <div class="size-10 shrink-0 overflow-hidden rounded-full bg-stone-800">
                                @if ($person['profile_path'])
                                    <img src="{{ rtrim(config('services.tmdb.image_base_url'), '/').'/w92'.$person['profile_path'] }}" alt="" class="size-full object-cover" loading="lazy" onerror="this.remove()">
                                @endif
                            </div>
                            <div class="min-w-0 text-sm">
                                <p class="truncate text-stone-200">{{ $person['name'] }}</p>
                                @if ($person['character'] !== '')
                                    <p class="truncate text-stone-500">{{ $person['character'] }}</p>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        <p class="text-xs text-stone-600">Filmdaten und Bilder: TMDB. Dieses Produkt verwendet die TMDB-API, ist aber nicht von TMDB unterstützt oder zertifiziert.</p>
    @elseif ($movie)
        <h1 class="text-2xl font-semibold tracking-tight">{{ $movie->title }} @if ($movie->year)<span class="font-normal text-stone-500">({{ $movie->year }})</span>@endif</h1>
    @endif
</div>
