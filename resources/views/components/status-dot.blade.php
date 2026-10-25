@props(['status'])

@php
    $label = match ($status->state) {
        \App\Services\WatchState::Seen => 'Gesehen',
        \App\Services\WatchState::OnWatchlist => 'Auf der Watchlist',
        \App\Services\WatchState::NeverSeen => 'Nie gesehen',
    };
@endphp

<span {{ $attributes->class([
    'inline-block size-2.5 shrink-0 rounded-full',
    'bg-red-500' => $status->state === \App\Services\WatchState::Seen,
    'bg-amber-400' => $status->state === \App\Services\WatchState::OnWatchlist,
    'bg-emerald-500' => $status->state === \App\Services\WatchState::NeverSeen,
]) }} title="{{ $label }}" role="img" aria-label="{{ $label }}"></span>
