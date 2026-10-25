<x-layouts::guest title="Anmelden">
    <form method="POST" action="{{ route('login.link.confirm', ['token' => $token]) }}" class="rounded-xl border border-stone-800 bg-stone-900/60 p-6 text-center">
        @csrf
        <p class="mb-5 text-stone-300">Fast geschafft – bestätige die Anmeldung.</p>
        <button type="submit"
                class="w-full rounded-lg bg-amber-400 py-2.5 font-medium text-stone-950 transition hover:bg-amber-300">
            Jetzt anmelden
        </button>
    </form>
</x-layouts::guest>
