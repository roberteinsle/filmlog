<div class="rounded-xl border border-stone-800 bg-stone-900/60 p-6">
    <form wire:submit="verify" class="space-y-5">
        <div>
            @if ($usesRecoveryCode)
                <label for="code" class="mb-1.5 block text-sm text-stone-400">Wiederherstellungscode</label>
                <input wire:model="code" id="code" type="text" autocomplete="off" required autofocus spellcheck="false"
                       class="w-full rounded-lg border border-stone-700 bg-stone-950 px-3 py-2 font-mono text-stone-100 outline-none focus:border-amber-400">
            @else
                <label for="code" class="mb-1.5 block text-sm text-stone-400">Code aus deiner Authenticator-App</label>
                <input wire:model="code" id="code" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="6" required autofocus
                       class="w-full rounded-lg border border-stone-700 bg-stone-950 px-3 py-2 text-center font-mono text-lg tracking-[0.4em] text-stone-100 outline-none focus:border-amber-400">
            @endif
            @error('code') <p class="mt-1.5 text-sm text-red-400">{{ $message }}</p> @enderror
        </div>

        <button type="submit" wire:loading.attr="disabled"
                class="w-full rounded-lg bg-amber-400 py-2.5 font-medium text-stone-950 transition hover:bg-amber-300 disabled:opacity-60">
            Anmelden
        </button>
    </form>

    <button type="button" wire:click="toggleRecoveryCode" class="mt-4 w-full text-sm text-stone-400 hover:text-stone-200">
        {{ $usesRecoveryCode ? 'Code aus der App verwenden' : 'Handy nicht zur Hand? Wiederherstellungscode verwenden' }}
    </button>
</div>
