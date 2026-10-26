@props(['status', 'details' => null])

@php
    $state = $status->state;
    $isExpandable = $details?->isNotEmpty() ?? false;
@endphp

{{-- Mit dem Slot $details lässt sich die Kachel aufklappen, z. B. für die einzelnen Sichtungen. --}}
<div x-data="{ open: false }" {{ $attributes->class([
    'rounded-xl border',
    'border-emerald-500/40 bg-emerald-500/10 text-emerald-100' => $state === \App\Services\WatchState::Seen,
    'border-amber-400/40 bg-amber-400/10 text-amber-100' => $state === \App\Services\WatchState::OnWatchlist,
    'border-red-500/40 bg-red-500/10 text-red-100' => $state === \App\Services\WatchState::NeverSeen,
]) }} role="status">
    <{{ $isExpandable ? 'button' : 'div' }}
        @if ($isExpandable) type="button" x-on:click="open = ! open" x-bind:aria-expanded="open" aria-controls="status-details" @endif
        class="flex w-full items-start gap-3 px-4 py-3 text-left">
        <span class="text-lg leading-6" aria-hidden="true">{{ match ($state) { \App\Services\WatchState::Seen => '🟢', \App\Services\WatchState::OnWatchlist => '🟡', \App\Services\WatchState::NeverSeen => '🔴' } }}</span>
        <span class="min-w-0 flex-1 text-sm leading-6">
            @if ($state === \App\Services\WatchState::Seen)
                <span class="block font-medium">{{ 'Gesehen am '.$status->lastWatchedAt->timezone(config('app.timezone'))->format('d.m.Y').($status->lastRating !== null ? ', deine Bewertung: '.$status->lastRating : '') }}</span>
                @if ($status->viewingCount > 1 || $status->onWatchlist)
                    <span class="block text-emerald-200/70">
                        {{ collect([
                            $status->viewingCount > 1 ? $status->viewingCount.'× gesehen' : null,
                            $status->onWatchlist ? 'steht außerdem auf der Watchlist' : null,
                        ])->filter()->join(' · ') }}
                    </span>
                @endif
            @elseif ($state === \App\Services\WatchState::OnWatchlist)
                <span class="block font-medium">Auf deiner Watchlist</span>
            @else
                <span class="block font-medium">Nie gesehen</span>
            @endif
        </span>
        @if ($isExpandable)
            <svg class="mt-0.5 size-5 shrink-0 opacity-70 transition-transform" x-bind:class="open && 'rotate-180'" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9l6 6 6-6" /></svg>
            <span class="sr-only">Sichtungen anzeigen</span>
        @endif
    </{{ $isExpandable ? 'button' : 'div' }}>

    @if ($isExpandable)
        <div id="status-details" x-show="open" x-cloak x-collapse>
            <div class="mx-4 mb-2 border-t border-current/15 pt-1 text-sm">
                {{ $details }}
            </div>
        </div>
    @endif
</div>
