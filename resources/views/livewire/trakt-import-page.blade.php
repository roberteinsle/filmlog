@php
    $statLabels = [
        'viewings_created' => 'Sichtungen neu',
        'viewings_existing' => 'Sichtungen schon vorhanden',
        'ratings_applied' => 'Bewertungen übernommen',
        'watchlist_added' => 'auf die Watchlist',
        'lists_created' => 'Listen angelegt',
        'list_items_added' => 'Filme auf Listen',
        'movies_unresolved' => 'Filme nicht zuordenbar',
        'skipped_non_movies' => 'Serien/Episoden übersprungen',
    ];
@endphp

<div @if ($this->isRunning) wire:poll.2s @endif>
    <a href="{{ route('settings') }}" wire:navigate class="mb-3 inline-flex items-center gap-1 text-sm text-stone-500 hover:text-stone-200">
        <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6" /></svg>
        Einstellungen
    </a>

    <x-page-header title="Trakt-Import">Verlauf, Bewertungen, Watchlist und Listen aus deinem Trakt-Export übernehmen.</x-page-header>

    <div class="max-w-2xl space-y-8">
        <section class="space-y-3 text-sm text-stone-400">
            <p>
                Lade die ZIP-Datei aus dem Trakt-Export hoch (bis {{ $maxUploadMb }} MB). Filmlog übernimmt nur Filme; Serien und Episoden werden übersprungen.
                Du kannst denselben Export auch mehrmals importieren, es entsteht nichts doppelt.
            </p>
            <ul class="list-inside list-disc space-y-1 text-stone-500">
                <li><span class="text-stone-300">Verlauf</span> wird zu Sichtungen mit Datum.</li>
                <li><span class="text-stone-300">Bewertungen</span> gehen an die letzte Sichtung des Films, wenn die noch keine hat. Ein bewerteter Film ohne Sichtung gilt als am Bewertungstag gesehen.</li>
                <li><span class="text-stone-300">Watchlist und Listen</span> werden in der Trakt-Reihenfolge ergänzt.</li>
            </ul>

            <form wire:submit="startImport" class="flex flex-wrap items-start gap-3 pt-2">
                <div class="min-w-0 flex-1">
                    <input type="file" wire:model="file" accept=".zip,application/zip" aria-label="Trakt-Export (ZIP)" @disabled($this->isRunning)
                           class="block w-full text-sm text-stone-400 file:mr-3 file:rounded-lg file:border-0 file:bg-stone-800 file:px-3 file:py-2 file:text-stone-200 hover:file:bg-stone-700">
                    <div wire:loading wire:target="file" class="mt-1 text-xs text-stone-500">lädt hoch …</div>
                    @error('file') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
                </div>
                <button type="submit" @disabled($this->isRunning) wire:loading.attr="disabled" wire:target="file,startImport"
                        class="rounded-lg bg-amber-400 px-4 py-2 font-semibold text-stone-950 transition hover:bg-amber-300 disabled:opacity-50">
                    Importieren
                </button>
            </form>
            @if ($this->isRunning)
                <p class="text-amber-300">Ein Import läuft gerade. Du kannst die Seite verlassen, er läuft im Hintergrund weiter.</p>
            @endif
        </section>

        @if ($this->imports->isNotEmpty())
            <section>
                <h2 class="mb-3 text-sm font-medium tracking-wide text-stone-500 uppercase">Letzte Importe</h2>
                <ul class="space-y-4">
                    @foreach ($this->imports as $import)
                        <li wire:key="import-{{ $import->id }}" class="rounded-xl border border-stone-800 p-4">
                            <div class="flex items-baseline justify-between gap-3">
                                <p class="truncate font-medium">{{ $import->original_name }}</p>
                                <span @class([
                                    'shrink-0 rounded px-2 py-0.5 text-xs',
                                    'bg-emerald-500/15 text-emerald-300' => $import->status === \App\Models\TraktImport::DONE,
                                    'bg-red-500/15 text-red-300' => $import->status === \App\Models\TraktImport::FAILED,
                                    'bg-amber-400/15 text-amber-200' => ! $import->isFinished(),
                                ])>{{ match ($import->status) { 'done' => 'fertig', 'failed' => 'fehlgeschlagen', 'running' => 'läuft', default => 'wartet' } }}</span>
                            </div>
                            <p class="mt-0.5 text-xs text-stone-500">{{ $import->created_at->timezone(config('app.timezone'))->format('d.m.Y H:i') }}</p>

                            @if (! $import->isFinished())
                                <div class="mt-3">
                                    <div class="h-2 overflow-hidden rounded-full bg-stone-800" role="progressbar" aria-valuenow="{{ $import->progress() }}" aria-valuemin="0" aria-valuemax="100">
                                        <div class="h-full rounded-full bg-amber-400 transition-all" style="width: {{ $import->progress() }}%"></div>
                                    </div>
                                    <p class="mt-1 text-xs text-stone-500">
                                        @if ($import->total_movies > 0)
                                            {{ $import->resolvedCount() }} von {{ $import->total_movies }} Filmen zugeordnet
                                        @else
                                            wird vorbereitet …
                                        @endif
                                    </p>
                                </div>
                            @endif

                            @if ($import->status === \App\Models\TraktImport::FAILED)
                                <p class="mt-3 text-sm text-red-300">{{ $import->error }}</p>
                            @endif

                            @if ($import->status === \App\Models\TraktImport::DONE)
                                <dl class="mt-3 grid grid-cols-2 gap-x-4 gap-y-1 text-sm sm:grid-cols-3">
                                    @foreach ($statLabels as $statKey => $label)
                                        <div class="flex items-baseline gap-2">
                                            <dt class="sr-only">{{ $label }}</dt>
                                            <dd class="font-semibold text-stone-100 tabular-nums">{{ $import->stats[$statKey] ?? 0 }}</dd>
                                            <span class="text-stone-500">{{ $label }}</span>
                                        </div>
                                    @endforeach
                                </dl>
                            @endif

                            @if ($import->unresolved !== [])
                                <details class="mt-3 text-sm">
                                    <summary class="cursor-pointer text-amber-300">{{ count($import->unresolved) }} {{ count($import->unresolved) === 1 ? 'Film' : 'Filme' }} nicht zuordenbar</summary>
                                    <ul class="mt-2 divide-y divide-stone-800/70">
                                        @foreach ($import->unresolved as $entry)
                                            <li class="py-1.5">
                                                <span class="text-stone-200">{{ $entry['title'] }}</span>
                                                @if ($entry['year']) <span class="text-stone-500">({{ $entry['year'] }})</span> @endif
                                                <span class="block text-xs text-stone-500">{{ $entry['reason'] }}@if ($entry['ids'] !== '') · {{ $entry['ids'] }}@endif</span>
                                            </li>
                                        @endforeach
                                    </ul>
                                </details>
                            @endif

                            @if ($import->export_errors !== [])
                                <details class="mt-3 text-sm" open>
                                    <summary class="cursor-pointer text-red-300">Der Trakt-Export meldet {{ count($import->export_errors) }} Fehler (_errors.json)</summary>
                                    <ul class="mt-2 space-y-1 font-mono text-xs break-all text-stone-400">
                                        @foreach ($import->export_errors as $exportError)
                                            <li>{{ $exportError }}</li>
                                        @endforeach
                                    </ul>
                                </details>
                            @endif

                            @if ($import->read_files !== [])
                                <p class="mt-2 text-xs text-stone-500">Gelesen (Film-Einträge): {{ implode(', ', $import->read_files) }}</p>
                            @endif

                            @if ($import->ignored_files !== [])
                                <p class="mt-1 text-xs text-stone-500">Nicht erkannte Dateien: {{ implode(', ', $import->ignored_files) }}</p>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
    </div>
</div>
