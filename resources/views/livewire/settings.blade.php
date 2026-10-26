<div>
    <x-page-header title="Einstellungen" />

    <section class="max-w-2xl">
        <h2 class="mb-1 text-lg font-semibold tracking-tight">Begleitungen</h2>
        <p class="mb-4 text-sm text-stone-400">Mit wem du Filme schaust. Die max. FSK nutzt später „Heute Abend“, damit es z. B. mit der Familie passt.</p>

        @if ($this->companions->isNotEmpty())
            <ul wire:sort="moveCompanion" class="mb-4 divide-y divide-stone-800 rounded-xl border border-stone-800">
                @foreach ($this->companions as $companion)
                    <li wire:key="companion-{{ $companion->id }}" wire:sort:item="{{ $companion->id }}" class="flex items-center gap-3 bg-stone-950 px-3 py-2.5 first:rounded-t-xl last:rounded-b-xl">
                        <button type="button" wire:sort:handle class="cursor-grab touch-none text-stone-600 hover:text-stone-300 active:cursor-grabbing" aria-label="{{ $companion->name }} verschieben">
                            <svg class="size-5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><circle cx="9" cy="6" r="1.5" /><circle cx="15" cy="6" r="1.5" /><circle cx="9" cy="12" r="1.5" /><circle cx="15" cy="12" r="1.5" /><circle cx="9" cy="18" r="1.5" /><circle cx="15" cy="18" r="1.5" /></svg>
                        </button>

                        @if ($editingId === $companion->id)
                            <form wire:submit="saveCompanion" wire:sort:ignore class="flex min-w-0 flex-1 flex-wrap items-center gap-2">
                                <input type="text" wire:model="editName" maxlength="50" aria-label="Name" required
                                       class="min-w-0 flex-1 rounded-lg border border-stone-700 bg-stone-900 px-3 py-1.5 text-sm outline-none focus:border-amber-400">
                                <select wire:model="editMaxFsk" aria-label="max. FSK" class="rounded-lg border border-stone-700 bg-stone-900 px-2 py-1.5 text-sm outline-none focus:border-amber-400">
                                    <option value="">ohne FSK-Grenze</option>
                                    @foreach ($fskLevels as $fsk)
                                        <option value="{{ $fsk }}">bis FSK {{ $fsk }}</option>
                                    @endforeach
                                </select>
                                <button type="submit" class="rounded-lg bg-amber-400 px-3 py-1.5 text-sm font-semibold text-stone-950 hover:bg-amber-300">Speichern</button>
                                <button type="button" wire:click="cancelEditing" class="px-2 py-1.5 text-sm text-stone-400 hover:text-stone-100">Abbrechen</button>
                                @error('editName') <p class="w-full text-sm text-red-400">{{ $message }}</p> @enderror
                                @error('editMaxFsk') <p class="w-full text-sm text-red-400">{{ $message }}</p> @enderror
                            </form>
                        @else
                            <div class="min-w-0 flex-1">
                                <p class="truncate">{{ $companion->name }}</p>
                                <p class="text-xs text-stone-500">
                                    {{ $companion->max_fsk !== null ? 'bis FSK '.$companion->max_fsk : 'ohne FSK-Grenze' }}
                                    · {{ $companion->viewings_count }} {{ $companion->viewings_count === 1 ? 'Sichtung' : 'Sichtungen' }}
                                </p>
                            </div>
                            <div class="flex shrink-0 gap-3 text-xs" wire:sort:ignore>
                                <button type="button" wire:click="startEditing({{ $companion->id }})" class="text-stone-500 hover:text-stone-200">Bearbeiten</button>
                                <button type="button" wire:click="deleteCompanion({{ $companion->id }})"
                                        wire:confirm="„{{ $companion->name }}“ löschen?{{ $companion->viewings_count > 0 ? ' Die Begleitung verschwindet auch aus '.$companion->viewings_count.' '.($companion->viewings_count === 1 ? 'Sichtung' : 'Sichtungen').'.' : '' }}"
                                        class="text-stone-500 hover:text-red-400">Löschen</button>
                            </div>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif

        <form wire:submit="addCompanion" class="flex flex-wrap items-start gap-2">
            <div class="min-w-0 flex-1">
                <input type="text" wire:model="name" maxlength="50" placeholder="Neue Begleitung, z. B. Jasmin" aria-label="Name der neuen Begleitung"
                       class="w-full rounded-lg border border-stone-700 bg-stone-900 px-3 py-2 text-sm outline-none placeholder:text-stone-600 focus:border-amber-400">
                @error('name') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
            </div>
            <select wire:model="maxFsk" aria-label="max. FSK der neuen Begleitung" class="rounded-lg border border-stone-700 bg-stone-900 px-2 py-2 text-sm outline-none focus:border-amber-400">
                <option value="">ohne FSK-Grenze</option>
                @foreach ($fskLevels as $fsk)
                    <option value="{{ $fsk }}">bis FSK {{ $fsk }}</option>
                @endforeach
            </select>
            <button type="submit" class="rounded-lg bg-amber-400 px-4 py-2 text-sm font-semibold text-stone-950 hover:bg-amber-300">Hinzufügen</button>
        </form>
    </section>

    <section class="mt-10 max-w-2xl">
        <h2 class="mb-1 text-lg font-semibold tracking-tight">Streaming-Abos</h2>
        <p class="mb-4 text-sm text-stone-400">Welche Abos du hast. Die Filmseite und „Heute Abend“ zeigen dann, ob ein Film in deinen Abos läuft.</p>

        @if ($this->streamingProviders['error'])
            <p class="mb-3 text-sm text-amber-300">{{ $this->streamingProviders['error'] }}</p>
        @endif

        <div class="flex flex-wrap gap-2">
            @foreach ($this->streamingProviders['providers'] as $provider)
                @php($isSubscribed = in_array($provider['id'], $subscribedProviderIds, true))
                <button type="button" wire:key="provider-{{ $provider['id'] }}" wire:click="toggleSubscription({{ $provider['id'] }})" aria-pressed="{{ $isSubscribed ? 'true' : 'false' }}"
                        @class([
                            'inline-flex items-center gap-2 rounded-full border py-1 pr-3 pl-1 text-sm transition',
                            'border-amber-400 bg-amber-400/15 text-amber-200' => $isSubscribed,
                            'border-stone-700 text-stone-300 hover:border-stone-500' => ! $isSubscribed,
                        ])>
                    <x-provider-logo :path="$provider['logo_path']" class="size-6 rounded-full" />
                    {{ $provider['name'] }}
                </button>
            @endforeach
        </div>
    </section>

    <section class="mt-10 max-w-2xl">
        <h2 class="mb-1 text-lg font-semibold tracking-tight">Plex</h2>
        <p class="mb-4 text-sm text-stone-400">Zu Ende geschaute Filme landen automatisch im Tagebuch; nachts gleicht Filmlog ab, welche Filme auf Plex liegen.</p>

        <dl class="space-y-3 text-sm">
            <div>
                <dt class="text-stone-500">Webhook-Adresse <span class="text-stone-600">(in Plex unter Einstellungen → Webhooks eintragen, braucht Plex Pass)</span></dt>
                <dd class="mt-1">
                    @if ($plexWebhookUrl)
                        <input type="text" readonly value="{{ $plexWebhookUrl }}" x-on:focus="$el.select()" aria-label="Webhook-Adresse"
                               class="w-full rounded-lg border border-stone-700 bg-stone-900 px-3 py-2 font-mono text-xs text-stone-300 outline-none focus:border-amber-400">
                    @else
                        <span class="text-amber-300">PLEX_WEBHOOK_SECRET ist nicht gesetzt.</span>
                    @endif
                </dd>
            </div>
            <div>
                <dt class="text-stone-500">Welche Plex-Konten ins Tagebuch schreiben</dt>
                <dd class="mt-1">
                    @if ($plexAccounts->isEmpty())
                        <span class="text-stone-500">Konten erscheinen hier, sobald sie in Plex etwas abspielen.</span>
                    @else
                        <ul class="space-y-1">
                            @foreach ($plexAccounts as $plexAccount)
                                <li wire:key="plex-account-{{ $plexAccount->id }}">
                                    <label class="flex cursor-pointer items-center gap-3 py-1">
                                        <input type="checkbox" wire:click="togglePlexAccount({{ $plexAccount->id }})" @checked($plexAccount->counts_viewings)
                                               class="size-4 rounded border-stone-600 bg-stone-900 accent-amber-400">
                                        <span class="text-stone-200">{{ $plexAccount->title }}</span>
                                        <span class="text-xs text-stone-600">zuletzt {{ $plexAccount->last_seen_at?->timezone(config('app.timezone'))->format('d.m.Y H:i') }}</span>
                                    </label>
                                </li>
                            @endforeach
                        </ul>
                        <p class="mt-1 text-xs text-stone-600">Fehlt ein Profil? Einfach in Plex mit diesem Profil kurz einen Film starten.</p>
                    @endif
                </dd>
            </div>
            <div>
                <dt class="text-stone-500">Bibliothek</dt>
                <dd class="mt-1 flex flex-wrap items-center gap-x-4 gap-y-2">
                    @if (! $plexIsConfigured)
                        <span class="text-amber-300">PLEX_URL und PLEX_TOKEN sind nicht gesetzt.</span>
                    @else
                        <span class="text-stone-300">
                            {{ $plexMovies }} {{ $plexMovies === 1 ? 'Film' : 'Filme' }} auf Plex
                            @if ($plexLastSync)
                                · abgeglichen {{ \Illuminate\Support\Carbon::parse($plexLastSync['at'])->timezone(config('app.timezone'))->format('d.m.Y H:i') }}
                            @else
                                · noch nie abgeglichen
                            @endif
                        </span>
                        @if ($plexSyncQueued)
                            <span class="text-amber-300">Abgleich läuft im Hintergrund – Seite gleich neu laden.</span>
                        @else
                            <button type="button" wire:click="syncPlex" class="text-amber-300 hover:text-amber-200">Jetzt abgleichen</button>
                        @endif
                    @endif
                </dd>
            </div>
        </dl>
    </section>

    <section class="mt-10 max-w-2xl">
        <h2 class="mb-1 text-lg font-semibold tracking-tight">Daten</h2>
        <p class="mb-4 text-sm text-stone-400">Verlauf, Bewertungen, Watchlist und Listen aus einem Trakt-Export übernehmen.</p>
        <a href="{{ route('import') }}" wire:navigate class="inline-flex items-center gap-2 rounded-lg border border-stone-700 px-4 py-2 text-sm font-medium text-stone-200 transition hover:border-stone-500">Trakt-Import öffnen</a>
    </section>
</div>
