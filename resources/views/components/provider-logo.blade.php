@props(['path' => null])

@if ($path)
    <img src="{{ rtrim(config('services.tmdb.image_base_url'), '/').'/w45'.$path }}" alt="" {{ $attributes->class('size-5 shrink-0 rounded') }} loading="lazy" onerror="this.remove()">
@endif
