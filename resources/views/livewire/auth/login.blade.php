<div x-data="{
        supportsPasskeys: window.Passkeys?.isSupported() ?? false,
        showEmail: @js($linkSent || $errors->any()),
        busy: false,
        passkeyError: null,
        async loginWithPasskey() {
            this.busy = true;
            this.passkeyError = null;
            try {
                const response = await window.Passkeys.verify();
                window.location.href = response?.redirect ?? '/';
            } catch (error) {
                if (error instanceof window.PasskeyErrors.UnexpectedResponseError && error.redirected) {
                    window.location.href = error.url;
                } else if (! (error instanceof window.PasskeyErrors.UserCancelledError)) {
                    this.passkeyError = 'Die Anmeldung mit Passkey hat nicht geklappt. Versuch es noch einmal oder nutze den Login-Link.';
                }
                this.busy = false;
            }
        },
     }"
     class="rounded-xl border border-stone-800 bg-stone-900/60 p-6">
    @session('status')
        <p class="mb-5 rounded-lg bg-stone-800 px-3 py-2 text-sm text-stone-300">{{ $value }}</p>
    @endsession

    <div x-show="supportsPasskeys" x-cloak class="space-y-3">
        <button type="button" x-on:click="loginWithPasskey" x-bind:disabled="busy"
                class="flex w-full items-center justify-center gap-2 rounded-lg bg-amber-400 py-2.5 font-medium text-stone-950 transition hover:bg-amber-300 disabled:opacity-60">
            <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M15 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0zM3 21v-1a6 6 0 0 1 9.5-4.9M17 14v7M17 14a2 2 0 1 0 0-4 2 2 0 0 0 0 4zM17 18h2" />
            </svg>
            Mit Passkey anmelden
        </button>
        <p x-show="passkeyError" x-text="passkeyError" class="text-sm text-red-400"></p>

        <button type="button" x-show="! showEmail" x-on:click="showEmail = true"
                class="w-full pt-1 text-sm text-stone-400 hover:text-stone-200">
            Kein Passkey zur Hand? Login-Link per E-Mail
        </button>
    </div>

    <div x-show="showEmail || ! supportsPasskeys" x-cloak x-bind:class="supportsPasskeys && 'mt-5 border-t border-stone-800 pt-5'">
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
            <form wire:submit="sendLink" class="space-y-5">
                <div>
                    <label for="email" class="mb-1.5 block text-sm text-stone-400">E-Mail</label>
                    <input wire:model="email" id="email" type="email" autocomplete="email" required
                           class="w-full rounded-lg border border-stone-700 bg-stone-950 px-3 py-2 text-stone-100 outline-none focus:border-amber-400">
                    @error('email') <p class="mt-1.5 text-sm text-red-400">{{ $message }}</p> @enderror
                </div>

                <button type="submit" wire:loading.attr="disabled"
                        x-bind:class="supportsPasskeys ? 'border border-stone-700 text-stone-200 hover:border-stone-500' : 'bg-amber-400 text-stone-950 hover:bg-amber-300'"
                        class="w-full rounded-lg py-2.5 font-medium transition disabled:opacity-60">
                    Login-Link senden
                </button>
            </form>
        @endif
    </div>
</div>
