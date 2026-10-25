<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
    <head>
        <x-head :title="$title ?? null" />
    </head>
    <body class="flex min-h-full items-center justify-center bg-stone-950 px-4 font-sans text-stone-100 antialiased">
        <div class="w-full max-w-sm">
            <p class="mb-8 text-center text-3xl font-semibold tracking-tight">
                Film<span class="text-amber-400">log</span>
            </p>
            {{ $slot }}
        </div>

        @livewireScripts
    </body>
</html>
