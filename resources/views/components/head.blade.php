<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#0c0a09">
<title>{{ $title ?? null ? $title.' · ' : '' }}{{ config('app.name') }}</title>

@fonts
@vite(['resources/css/app.css', 'resources/js/app.js'])
@livewireStyles
