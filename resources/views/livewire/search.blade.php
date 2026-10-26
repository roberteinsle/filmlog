<div>
    <x-page-header title="Suche" />

    <div class="relative mb-6">
        <svg class="pointer-events-none absolute top-1/2 left-3 size-5 -translate-y-1/2 text-stone-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <path stroke-linecap="round" d="M21 21l-4.35-4.35M10.5 18a7.5 7.5 0 1 1 0-15 7.5 7.5 0 0 1 0 15z" />
        </svg>
        <input wire:model.live.debounce.400ms="query" type="search" inputmode="search" autofocus maxlength="100"
               placeholder="Filmtitel suchen …" aria-label="Filmtitel suchen"
               class="w-full rounded-xl border border-stone-700 bg-stone-900 py-3 pr-4 pl-11 text-base text-stone-100 outline-none placeholder:text-stone-500 focus:border-amber-400">
        <div wire:loading.delay class="absolute top-1/2 right-4 -translate-y-1/2 text-xs text-stone-500">sucht …</div>
    </div>

    @php($results = $this->results)

    @if ($this->error)
        <x-placeholder>{{ $this->error }}</x-placeholder>
    @elseif (mb_strlen(trim($query)) < $minQueryLength)
        <x-placeholder>Gib mindestens {{ $minQueryLength }} Zeichen ein.</x-placeholder>
    @elseif ($results === [])
        <x-placeholder>Kein Film gefunden für „{{ $query }}“.</x-placeholder>
    @else
        <ul class="divide-y divide-stone-800/70">
            @foreach ($results as $result)
                <li wire:key="result-{{ $result['id'] }}" class="flex items-center gap-2">
                    <a href="{{ route('movie', $result['id']) }}" wire:navigate class="flex min-w-0 flex-1 gap-4 py-3 transition hover:bg-stone-900/60">
                        <x-poster :path="$result['poster_path']" :alt="$result['title']" size="w92" class="w-14 shrink-0" />
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <x-status-dot :status="$result['status']" />
                                <p class="truncate font-medium">{{ $result['title'] }}</p>
                            </div>
                            <p class="mt-0.5 text-sm text-stone-500">{{ $result['year'] ?? 'Jahr unbekannt' }}</p>
                            @if ($result['overview'] !== '')
                                <p class="mt-1 line-clamp-2 text-sm text-stone-400">{{ $result['overview'] }}</p>
                            @endif
                        </div>
                    </a>
                    <livewire:add-to-list :tmdb-id="$result['id']" variant="icon" :key="'add-to-list-'.$result['id']" />
                </li>
            @endforeach
        </ul>
    @endif
</div>
