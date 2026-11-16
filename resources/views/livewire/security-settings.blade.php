<section class="mt-10 max-w-2xl"
         x-data="{
            passkeyName: '',
            busy: false,
            passkeyError: null,
            async addPasskey() {
                this.busy = true;
                this.passkeyError = null;
                try {
                    await window.Passkeys.register({ name: this.passkeyName.trim() || 'Passkey' });
                    this.passkeyName = '';
                    $wire.$refresh();
                } catch (error) {
                    if (! (error instanceof window.PasskeyErrors.UserCancelledError)) {
                        this.passkeyError = error instanceof window.PasskeyErrors.PasskeyExistsError
                            ? 'Für dieses Gerät gibt es schon einen Passkey.'
                            : 'Der Passkey konnte nicht gespeichert werden.';
                    }
                }
                this.busy = false;
            },
            async confirmWithPasskey() {
                this.busy = true;
                this.passkeyError = null;
                try {
                    await window.Passkeys.verify({ routes: { options: '{{ route('passkey.confirm-options') }}', submit: '{{ route('passkey.confirm') }}' } });
                    $wire.$refresh();
                } catch (error) {
                    if (! (error instanceof window.PasskeyErrors.UserCancelledError)) {
                        this.passkeyError = 'Die Bestätigung mit Passkey hat nicht geklappt.';
                    }
                }
                this.busy = false;
            },
         }">
    <h2 class="mb-1 text-lg font-semibold tracking-tight">Sicherheit</h2>
    <p class="mb-4 text-sm text-stone-400">
        Standard-Login ist ein Passkey (Face ID, Touch ID, Windows Hello oder Passwort-Manager).
        Ohne Passkey geht es per Login-Link aus der Mail, mit eingerichteter Authenticator-App zusätzlich mit Code.
    </p>

    @unless ($this->isConfirmed)
        <div class="mb-4 rounded-lg border border-stone-800 bg-stone-900/60 px-3 py-2.5 text-sm text-stone-300">
            @if ($this->passkeys->isNotEmpty())
                Zum Ändern kurz bestätigen.
                <button type="button" x-on:click="confirmWithPasskey" x-bind:disabled="busy" class="ml-1 font-medium text-amber-400 hover:text-amber-300 disabled:opacity-60">Mit Passkey bestätigen</button>
            @else
                Zum Ändern bitte ab- und neu anmelden.
            @endif
        </div>
    @endunless
    @error('security') <p class="mb-4 text-sm text-red-400">{{ $message }}</p> @enderror
    <p x-show="passkeyError" x-text="passkeyError" x-cloak class="mb-4 text-sm text-red-400"></p>

    <h3 class="mb-2 text-sm font-medium text-stone-300">Passkeys</h3>
    @if ($this->passkeys->isEmpty())
        <p class="mb-3 rounded-lg border border-amber-400/30 bg-amber-400/5 px-3 py-2.5 text-sm text-amber-200">
            Noch kein Passkey. Leg einen an, dann klappt der Login ohne Mail.
        </p>
    @else
        <ul class="mb-3 divide-y divide-stone-800 rounded-xl border border-stone-800">
            @foreach ($this->passkeys as $passkey)
                <li wire:key="passkey-{{ $passkey->id }}" class="flex items-center gap-3 px-3 py-2.5">
                    <div class="min-w-0 flex-1">
                        <p class="truncate">{{ $passkey->name }}</p>
                        <p class="text-xs text-stone-500">
                            {{ $passkey->authenticator ? $passkey->authenticator.' · ' : '' }}angelegt {{ $passkey->created_at->isoFormat('D. MMM YYYY') }}
                            · {{ $passkey->last_used_at ? 'zuletzt genutzt '.$passkey->last_used_at->diffForHumans() : 'noch nie genutzt' }}
                        </p>
                    </div>
                    @if ($this->isConfirmed)
                        <button type="button" wire:click="deletePasskey({{ $passkey->id }})"
                                wire:confirm="Passkey „{{ $passkey->name }}“ löschen?"
                                class="shrink-0 text-xs text-stone-500 hover:text-red-400">Löschen</button>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif

    @if ($this->isConfirmed)
        <form x-on:submit.prevent="addPasskey" x-show="window.Passkeys?.isSupported()" class="flex flex-wrap gap-2">
            <input type="text" x-model="passkeyName" maxlength="50" placeholder="Name, z. B. iPhone" aria-label="Name des neuen Passkeys"
                   class="min-w-0 flex-1 rounded-lg border border-stone-700 bg-stone-900 px-3 py-2 text-sm outline-none placeholder:text-stone-600 focus:border-amber-400">
            <button type="submit" x-bind:disabled="busy"
                    class="rounded-lg bg-amber-400 px-4 py-2 text-sm font-semibold text-stone-950 hover:bg-amber-300 disabled:opacity-60">Passkey hinzufügen</button>
        </form>
    @endif

    <h3 class="mt-8 mb-2 text-sm font-medium text-stone-300">Authenticator-App</h3>

    @if ($recoveryCodes !== [])
        <div class="mb-4 rounded-xl border border-amber-400/30 bg-amber-400/5 p-4">
            <p class="mb-3 text-sm text-amber-200">Wiederherstellungscodes: Jeder funktioniert genau einmal statt eines App-Codes. Speichere sie jetzt sicher ab, sie werden nicht noch einmal angezeigt.</p>
            <ul class="mb-3 grid grid-cols-2 gap-1.5 font-mono text-sm text-stone-100">
                @foreach ($recoveryCodes as $recoveryCode)
                    <li wire:key="recovery-{{ $loop->index }}">{{ $recoveryCode }}</li>
                @endforeach
            </ul>
            <button type="button" wire:click="hideRecoveryCodes" class="text-sm font-medium text-amber-400 hover:text-amber-300">Gespeichert, ausblenden</button>
        </div>
    @endif

    @if ($twoFactorEnabled)
        <p class="mb-3 text-sm text-stone-400">
            Aktiv. Nach dem Login-Link fragt Filmlog den Code ab.
            Noch {{ $remainingRecoveryCodes }} {{ $remainingRecoveryCodes === 1 ? 'Wiederherstellungscode' : 'Wiederherstellungscodes' }} übrig.
        </p>
        @if ($this->isConfirmed)
            <div class="flex flex-wrap gap-4 text-sm">
                <button type="button" wire:click="regenerateRecoveryCodes"
                        wire:confirm="Neue Wiederherstellungscodes erzeugen? Die alten werden ungültig."
                        class="text-stone-300 hover:text-stone-100">Neue Wiederherstellungscodes</button>
                <button type="button" wire:click="disableTwoFactor"
                        wire:confirm="Authenticator-App entfernen? Dann reicht der Login-Link allein."
                        class="text-stone-500 hover:text-red-400">Entfernen</button>
            </div>
        @endif
    @elseif ($pendingSecret)
        <div class="space-y-3 rounded-xl border border-stone-800 p-4">
            <p class="text-sm text-stone-300">
                Füge in deiner Authenticator-App ein neues Konto hinzu
                (<a href="{{ $setupUrl }}" class="text-amber-400 hover:text-amber-300">am Handy direkt öffnen</a>)
                oder tippe diesen Schlüssel ein:
            </p>
            <p class="rounded-lg bg-stone-900 px-3 py-2 font-mono text-sm break-all text-stone-100 select-all">{{ trim(chunk_split($pendingSecret, 4, ' ')) }}</p>
            <form wire:submit="confirmTwoFactorSetup" class="flex flex-wrap items-start gap-2">
                <div class="min-w-0 flex-1">
                    <input type="text" wire:model="setupCode" inputmode="numeric" autocomplete="one-time-code" maxlength="6" placeholder="6-stelliger Code" aria-label="Code aus der Authenticator-App"
                           class="w-full rounded-lg border border-stone-700 bg-stone-900 px-3 py-2 font-mono text-sm outline-none placeholder:font-sans placeholder:text-stone-600 focus:border-amber-400">
                    @error('setupCode') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
                </div>
                <button type="submit" class="rounded-lg bg-amber-400 px-4 py-2 text-sm font-semibold text-stone-950 hover:bg-amber-300">Bestätigen</button>
                <button type="button" wire:click="cancelTwoFactorSetup" class="px-2 py-2 text-sm text-stone-400 hover:text-stone-100">Abbrechen</button>
            </form>
        </div>
    @else
        <p class="mb-3 text-sm text-stone-400">Nicht eingerichtet: Ohne Passkey reicht im Moment der Login-Link allein.</p>
        @if ($this->isConfirmed)
            <button type="button" wire:click="startTwoFactorSetup"
                    class="rounded-lg border border-stone-700 px-4 py-2 text-sm font-medium text-stone-200 transition hover:border-stone-500">Authenticator-App einrichten</button>
        @endif
    @endif
</section>
