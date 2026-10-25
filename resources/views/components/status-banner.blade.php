@props(['status'])

@php($state = $status->state)

<div {{ $attributes->class([
    'flex items-start gap-3 rounded-xl border px-4 py-3',
    'border-red-500/40 bg-red-500/10 text-red-100' => $state === \App\Services\WatchState::Seen,
    'border-amber-400/40 bg-amber-400/10 text-amber-100' => $state === \App\Services\WatchState::OnWatchlist,
    'border-emerald-500/40 bg-emerald-500/10 text-emerald-100' => $state === \App\Services\WatchState::NeverSeen,
]) }} role="status">
    <span class="text-lg leading-6" aria-hidden="true">{{ match ($state) { \App\Services\WatchState::Seen => '🔴', \App\Services\WatchState::OnWatchlist => '🟡', \App\Services\WatchState::NeverSeen => '🟢' } }}</span>
    <div class="text-sm leading-6">
        @if ($state === \App\Services\WatchState::Seen)
            <p class="font-medium">{{ 'Gesehen am '.$status->lastWatchedAt->timezone(config('app.timezone'))->format('d.m.Y').($status->lastRating !== null ? ', deine Bewertung: '.$status->lastRating : '') }}</p>
            @if ($status->viewingCount > 1 || $status->onWatchlist)
                <p class="text-red-200/70">
                    {{ collect([
                        $status->viewingCount > 1 ? $status->viewingCount.'× gesehen' : null,
                        $status->onWatchlist ? 'steht außerdem auf der Watchlist' : null,
                    ])->filter()->join(' · ') }}
                </p>
            @endif
        @elseif ($state === \App\Services\WatchState::OnWatchlist)
            <p class="font-medium">Auf deiner Watchlist</p>
        @else
            <p class="font-medium">Nie gesehen</p>
        @endif
    </div>
</div>
