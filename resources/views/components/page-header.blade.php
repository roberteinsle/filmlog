@props(['title'])

<div class="mb-6">
    <h1 class="text-2xl font-semibold tracking-tight">{{ $title }}</h1>
    @if ($slot->isNotEmpty())
        <p class="mt-1 text-stone-400">{{ $slot }}</p>
    @endif
</div>
