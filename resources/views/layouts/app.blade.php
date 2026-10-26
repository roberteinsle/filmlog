@php
    $navigation = [
        ['route' => 'tonight', 'label' => 'Heute Abend', 'icon' => 'M3 12h18M3 6h18M3 18h12'],
        ['route' => 'search', 'active' => ['search', 'movie'], 'label' => 'Suche', 'icon' => 'M21 21l-4.35-4.35M10.5 18a7.5 7.5 0 1 1 0-15 7.5 7.5 0 0 1 0 15z'],
        ['route' => 'lists', 'label' => 'Listen', 'icon' => 'M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01'],
        ['route' => 'diary', 'label' => 'Tagebuch', 'icon' => 'M8 2v4M16 2v4M3 10h18M5 4h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2z'],
        ['route' => 'review', 'label' => 'Rückblick', 'icon' => 'M3 3v18h18M7 15l4-4 3 3 5-6'],
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
    <head>
        <x-head :title="$title ?? null" />
    </head>
    <body class="min-h-full bg-stone-950 font-sans text-stone-100 antialiased">
        <header class="sticky top-0 z-20 border-b border-stone-800/80 bg-stone-950/90 pt-[env(safe-area-inset-top)] backdrop-blur">
            <div class="mx-auto flex h-14 max-w-5xl items-center justify-between gap-4 px-4">
                <div class="flex items-baseline gap-2">
                    <a href="{{ route('tonight') }}" wire:navigate class="text-lg font-semibold tracking-tight">
                        Film<span class="text-amber-400">log</span>
                    </a>
                    <x-build-info />
                </div>

                <nav class="hidden items-center gap-1 md:flex" aria-label="Hauptnavigation">
                    @foreach ($navigation as $item)
                        <a href="{{ route($item['route']) }}" wire:navigate
                           @class([
                               'rounded-md px-3 py-1.5 text-sm transition',
                               'bg-stone-800 text-amber-300' => request()->routeIs(...(array) ($item['active'] ?? $item['route'])),
                               'text-stone-400 hover:text-stone-100' => ! request()->routeIs(...(array) ($item['active'] ?? $item['route'])),
                           ])>
                            {{ $item['label'] }}
                        </a>
                    @endforeach
                </nav>

                <div class="flex items-center gap-4">
                    <a href="{{ route('settings') }}" wire:navigate aria-label="Einstellungen" title="Einstellungen"
                       @class(['transition', 'text-amber-300' => request()->routeIs('settings'), 'text-stone-500 hover:text-stone-200' => ! request()->routeIs('settings')])>
                        <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6z" />
                            <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z" />
                        </svg>
                    </a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="text-sm text-stone-500 hover:text-stone-200">Abmelden</button>
                    </form>
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-5xl px-4 pt-6 pb-28 md:pb-12">
            {{ $slot }}
        </main>

        <nav class="fixed inset-x-0 bottom-0 z-20 border-t border-stone-800 bg-stone-950/95 pb-[env(safe-area-inset-bottom)] backdrop-blur md:hidden" aria-label="Hauptnavigation">
            <div class="grid grid-cols-5">
                @foreach ($navigation as $item)
                    <a href="{{ route($item['route']) }}" wire:navigate
                       @class([
                           'flex flex-col items-center gap-1 py-2.5 text-[11px]',
                           'text-amber-300' => request()->routeIs(...(array) ($item['active'] ?? $item['route'])),
                           'text-stone-500' => ! request()->routeIs(...(array) ($item['active'] ?? $item['route'])),
                       ])>
                        <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="{{ $item['icon'] }}" />
                        </svg>
                        {{ $item['label'] }}
                    </a>
                @endforeach
            </div>
        </nav>

        @livewireScripts
    </body>
</html>
