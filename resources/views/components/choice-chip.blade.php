@props(['active' => false])

<button type="button" aria-pressed="{{ $active ? 'true' : 'false' }}" {{ $attributes->class([
    'rounded-full border px-3.5 py-1.5 text-sm transition',
    'border-amber-400 bg-amber-400/15 text-amber-200' => $active,
    'border-stone-700 text-stone-300 hover:border-stone-500' => ! $active,
]) }}>{{ $slot }}</button>
