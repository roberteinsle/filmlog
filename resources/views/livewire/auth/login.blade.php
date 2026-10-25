<form wire:submit="login" class="space-y-5 rounded-xl border border-stone-800 bg-stone-900/60 p-6">
    <div>
        <label for="email" class="mb-1.5 block text-sm text-stone-400">E-Mail</label>
        <input wire:model="email" id="email" type="email" autocomplete="username" required autofocus
               class="w-full rounded-lg border border-stone-700 bg-stone-950 px-3 py-2 text-stone-100 outline-none focus:border-amber-400">
        @error('email') <p class="mt-1.5 text-sm text-red-400">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="password" class="mb-1.5 block text-sm text-stone-400">Passwort</label>
        <input wire:model="password" id="password" type="password" autocomplete="current-password" required
               class="w-full rounded-lg border border-stone-700 bg-stone-950 px-3 py-2 text-stone-100 outline-none focus:border-amber-400">
        @error('password') <p class="mt-1.5 text-sm text-red-400">{{ $message }}</p> @enderror
    </div>

    <label class="flex items-center gap-2 text-sm text-stone-400">
        <input wire:model="remember" type="checkbox" class="accent-amber-400">
        Angemeldet bleiben
    </label>

    <button type="submit" wire:loading.attr="disabled"
            class="w-full rounded-lg bg-amber-400 py-2.5 font-medium text-stone-950 transition hover:bg-amber-300 disabled:opacity-60">
        Anmelden
    </button>
</form>
