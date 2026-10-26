@props(['title', 'close'])

{{-- Dialog als Bottom-Sheet (mobil) bzw. zentriert (Desktop); Escape und Klick daneben schließen ihn.
     Teleport an das Body-Ende, damit Abstände und Stacking der Seite den Dialog nicht beeinflussen. --}}
@teleport('body')
<div class="fixed inset-0 z-30 flex items-end justify-center bg-black/70 sm:items-center sm:p-4"
     x-data x-on:keydown.escape.window="$wire.{{ $close }}()" x-on:click.self="$wire.{{ $close }}()"
     role="dialog" aria-modal="true" aria-labelledby="modal-title">
    <div {{ $attributes->class('max-h-[90vh] w-full max-w-md overflow-y-auto rounded-t-2xl border border-stone-800 bg-stone-900 p-5 pb-[calc(1.25rem+env(safe-area-inset-bottom))] shadow-2xl sm:rounded-2xl sm:pb-5') }}>
        <h2 id="modal-title" class="mb-4 text-lg font-semibold tracking-tight">{{ $title }}</h2>
        {{ $slot }}
    </div>
</div>
@endteleport
