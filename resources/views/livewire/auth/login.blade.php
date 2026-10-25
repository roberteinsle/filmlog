<div class="rounded-xl border border-stone-800 bg-stone-900/60 p-6">
    @if ($linkSent)
        <div class="space-y-2 text-center">
            <p class="font-medium">Schau in dein Postfach.</p>
            <p class="text-sm text-stone-400">
                Wenn die Adresse bekannt ist, ist ein Login-Link unterwegs. Er ist {{ config('filmlog.login_link_minutes') }} Minuten gültig.
            </p>
            <button type="button" wire:click="$set('linkSent', false)" class="pt-2 text-sm text-amber-400 hover:text-amber-300">
                Andere Adresse verwenden
            </button>
        </div>
    @else
        @session('status')
            <p class="mb-5 rounded-lg bg-stone-800 px-3 py-2 text-sm text-stone-300">{{ $value }}</p>
        @endsession

        <form wire:submit="sendLink" class="space-y-5">
            <div>
                <label for="email" class="mb-1.5 block text-sm text-stone-400">E-Mail</label>
                <input wire:model="email" id="email" type="email" autocomplete="email" required autofocus
                       class="w-full rounded-lg border border-stone-700 bg-stone-950 px-3 py-2 text-stone-100 outline-none focus:border-amber-400">
                @error('email') <p class="mt-1.5 text-sm text-red-400">{{ $message }}</p> @enderror
            </div>

            <button type="submit" wire:loading.attr="disabled"
                    class="w-full rounded-lg bg-amber-400 py-2.5 font-medium text-stone-950 transition hover:bg-amber-300 disabled:opacity-60">
                Login-Link senden
            </button>
        </form>
    @endif
</div>
