@props(['list'])

<a href="{{ route('lists.show', $list) }}" wire:navigate {{ $attributes->class('flex min-w-0 flex-1 items-center gap-4') }}>
    {{-- Bis zu vier Poster überlappend: 44px + 3 × 20px = 104px --}}
    <div class="flex h-16 w-[104px] shrink-0 -space-x-6">
        @forelse ($list->movies as $movie)
            @if ($movie->poster_path)
                <x-poster :path="$movie->poster_path" :alt="$movie->title" size="w92" class="h-16 w-11 shrink-0 shadow-md shadow-black/60 ring-1 ring-stone-950" />
            @else
                <div class="h-16 w-11 shrink-0 rounded-md bg-stone-800 shadow-md shadow-black/60 ring-1 ring-stone-950" title="{{ $movie->title }}"></div>
            @endif
        @empty
            <div class="flex h-16 w-11 items-center justify-center rounded-md border border-dashed border-stone-700 text-stone-600">∅</div>
        @endforelse
    </div>
    <div class="min-w-0">
        <p class="truncate font-medium">{{ $list->name }}</p>
        <p class="text-sm text-stone-500">{{ $list->movies_count }} {{ $list->movies_count === 1 ? 'Film' : 'Filme' }}</p>
    </div>
</a>
