@props(['path' => null, 'alt' => '', 'size' => 'w342'])

<div {{ $attributes->class('aspect-[2/3] overflow-hidden rounded-md bg-stone-800') }}>
    @if ($path)
        <img src="{{ rtrim(config('services.tmdb.image_base_url'), '/').'/'.$size.$path }}" alt="Poster: {{ $alt }}"
             loading="lazy" decoding="async" class="size-full object-cover" onerror="this.remove()">
    @else
        <div class="flex size-full items-center justify-center p-1 text-center text-[10px] text-stone-500">kein Poster</div>
    @endif
</div>
