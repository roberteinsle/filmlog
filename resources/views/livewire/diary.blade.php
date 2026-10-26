<div>
    <x-page-header title="Tagebuch">Alle Sichtungen chronologisch, mit Bewertung, Begleitung und Notiz.</x-page-header>

    @if ($months->isEmpty())
        <x-placeholder>
            Noch keine Sichtungen.
            <a href="{{ route('search') }}" wire:navigate class="text-amber-300 underline-offset-2 hover:underline">Such einen Film</a>
            und trag ihn als gesehen ein.
        </x-placeholder>
    @else
        <div class="space-y-8">
            @foreach ($months as $month => $viewings)
                <section wire:key="month-{{ $month }}">
                    <h2 class="sticky top-14 z-10 -mx-4 mb-2 flex items-baseline justify-between bg-stone-950/95 px-4 py-2 text-sm font-medium tracking-wide text-stone-400 uppercase backdrop-blur">
                        {{ \Illuminate\Support\Carbon::createFromFormat('!Y-m', $month)->translatedFormat('F Y') }}
                        <span class="text-xs font-normal tracking-normal text-stone-600 normal-case">{{ $viewings->count() }} {{ $viewings->count() === 1 ? 'Film' : 'Filme' }}</span>
                    </h2>

                    <ol class="divide-y divide-stone-800/70">
                        @foreach ($viewings as $viewing)
                            @php($watchedAt = $viewing->watched_at->timezone(config('app.timezone')))
                            <li wire:key="viewing-{{ $viewing->id }}" class="flex gap-4 py-3">
                                <div class="w-10 shrink-0 pt-0.5 text-center">
                                    <p class="text-lg leading-none font-semibold text-stone-200">{{ $watchedAt->format('d') }}</p>
                                    <p class="mt-1 text-xs text-stone-500">{{ $watchedAt->translatedFormat('D') }}</p>
                                </div>

                                <a href="{{ route('movie', $viewing->tmdb_id) }}" wire:navigate class="shrink-0">
                                    <x-poster :path="$viewing->movie->poster_path" :alt="$viewing->movie->title" size="w92" class="w-12" />
                                </a>

                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-baseline gap-x-2 gap-y-1">
                                        <a href="{{ route('movie', $viewing->tmdb_id) }}" wire:navigate class="font-medium hover:text-amber-300">{{ $viewing->movie->title }}</a>
                                        @if ($viewing->movie->year)
                                            <span class="text-sm text-stone-500">{{ $viewing->movie->year }}</span>
                                        @endif
                                        @if ($viewing->viewing_number > 1)
                                            <span class="rounded bg-sky-500/15 px-1.5 py-0.5 text-xs text-sky-300">{{ $viewing->viewing_number }}. Sichtung</span>
                                        @endif
                                        @if ($viewing->source !== 'manual')
                                            <span class="rounded border border-stone-700 px-1.5 py-0.5 text-xs text-stone-500">{{ ucfirst($viewing->source) }}</span>
                                        @endif
                                    </div>

                                    <p class="mt-1 text-sm text-stone-400">
                                        @if ($viewing->rating !== null)
                                            <span class="font-semibold text-amber-300">{{ $viewing->rating }}</span><span class="text-stone-600">/10</span> ·
                                        @endif
                                        {{ $viewing->companions->isEmpty() ? 'allein' : 'mit '.$viewing->companions->pluck('name')->join(', ', ' und ') }}
                                    </p>

                                    @if ($viewing->note)
                                        <p class="mt-1 text-sm text-stone-300 italic">„{{ $viewing->note }}“</p>
                                    @endif
                                </div>

                                <div class="flex shrink-0 flex-col items-end gap-1 text-xs sm:flex-row sm:items-start sm:gap-3">
                                    <button type="button" wire:click="editViewing({{ $viewing->id }})" class="text-stone-500 hover:text-stone-200">Bearbeiten</button>
                                    <button type="button" wire:click="deleteViewing({{ $viewing->id }})"
                                            wire:confirm="Sichtung von „{{ $viewing->movie->title }}“ am {{ $watchedAt->format('d.m.Y') }} löschen?"
                                            class="text-stone-500 hover:text-red-400">Löschen</button>
                                </div>
                            </li>
                        @endforeach
                    </ol>
                </section>
            @endforeach
        </div>

        @if ($hasMore)
            <div class="mt-6 text-center">
                <button type="button" wire:click="showMore" class="rounded-lg border border-stone-700 px-4 py-2 text-sm text-stone-300 hover:border-stone-500">Ältere Sichtungen anzeigen</button>
            </div>
        @endif
    @endif

    @if ($showViewingDialog && $form->viewing)
        <x-modal :title="'Sichtung von „'.$form->viewing->movie->title.'“ bearbeiten'" close="closeViewingDialog">
            <x-viewing-form :companions="$this->companions" submit="updateViewing" cancel="closeViewingDialog" />
        </x-modal>
    @endif
</div>
