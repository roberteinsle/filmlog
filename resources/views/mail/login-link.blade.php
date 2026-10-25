<x-mail::message>
# Anmelden bei Filmlog

Klick auf den Button, um dich anzumelden. Der Link ist {{ $validMinutes }} Minuten gültig und funktioniert nur einmal.

<x-mail::button :url="$url">
Bei Filmlog anmelden
</x-mail::button>

Du hast keinen Login angefordert? Dann kannst du diese Mail einfach ignorieren.

<x-mail::subcopy>
Falls der Button nicht funktioniert, kopiere diesen Link in den Browser: [{{ $url }}]({{ $url }})
</x-mail::subcopy>
</x-mail::message>
