<?php

namespace App\Livewire;

use App\Models\User;
use App\Services\LoginSession;
use App\Services\TwoFactorAuthentication;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Laravel\Passkeys\Actions\DeletePasskey;
use Laravel\Passkeys\Passkey;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Einstellungen › Sicherheit: Passkeys (Standard-Login) und Authenticator-App
 * (zweiter Faktor beim Magic Link). Änderungen nur mit frischer Bestätigung.
 */
class SecuritySettings extends Component
{
    private const string PENDING_SECRET_KEY = 'two_factor.pending_secret';

    public string $setupCode = '';

    /**
     * Klartext-Wiederherstellungscodes, nur direkt nach dem Erzeugen sichtbar.
     *
     * @var list<string>
     */
    public array $recoveryCodes = [];

    /**
     * @return Collection<int, Passkey>
     */
    #[Computed]
    public function passkeys(): Collection
    {
        return $this->user()->passkeys()->latest()->get();
    }

    #[Computed]
    public function isConfirmed(): bool
    {
        return app(LoginSession::class)->isRecentlyConfirmed();
    }

    public function deletePasskey(int $passkeyId, DeletePasskey $deletePasskey): void
    {
        $this->ensureConfirmed();

        $passkey = $this->user()->passkeys()->findOrFail($passkeyId);
        $deletePasskey($this->user(), $passkey);

        unset($this->passkeys);
    }

    public function startTwoFactorSetup(TwoFactorAuthentication $twoFactor): void
    {
        $this->ensureConfirmed();

        session()->put(self::PENDING_SECRET_KEY, $twoFactor->generateSecret());
        $this->reset('setupCode');
    }

    public function cancelTwoFactorSetup(): void
    {
        session()->forget(self::PENDING_SECRET_KEY);
        $this->reset('setupCode');
        $this->resetErrorBag();
    }

    public function confirmTwoFactorSetup(TwoFactorAuthentication $twoFactor): void
    {
        $this->ensureConfirmed();

        $secret = session(self::PENDING_SECRET_KEY);

        if (! is_string($secret) || ! $twoFactor->verifySecret($secret, $this->setupCode)) {
            $this->reset('setupCode');

            throw ValidationException::withMessages(['setupCode' => 'Der Code passt nicht. Prüfe die Uhrzeit am Handy und versuch den nächsten Code.']);
        }

        $twoFactor->enable($this->user(), $secret);
        $this->recoveryCodes = $twoFactor->regenerateRecoveryCodes($this->user());

        session()->forget(self::PENDING_SECRET_KEY);
        $this->reset('setupCode');
    }

    public function regenerateRecoveryCodes(TwoFactorAuthentication $twoFactor): void
    {
        $this->ensureConfirmed();

        if ($this->user()->hasTwoFactorEnabled()) {
            $this->recoveryCodes = $twoFactor->regenerateRecoveryCodes($this->user());
        }
    }

    public function hideRecoveryCodes(): void
    {
        $this->reset('recoveryCodes');
    }

    public function disableTwoFactor(TwoFactorAuthentication $twoFactor): void
    {
        $this->ensureConfirmed();

        $twoFactor->disable($this->user());
        $this->reset('recoveryCodes');
    }

    public function render(TwoFactorAuthentication $twoFactor): View
    {
        $user = $this->user();
        $pendingSecret = session(self::PENDING_SECRET_KEY);

        return view('livewire.security-settings', [
            'twoFactorEnabled' => $user->hasTwoFactorEnabled(),
            'pendingSecret' => is_string($pendingSecret) ? $pendingSecret : null,
            'setupUrl' => is_string($pendingSecret) ? $twoFactor->setupUrl($user, $pendingSecret) : null,
            'remainingRecoveryCodes' => $twoFactor->remainingRecoveryCodes($user),
        ]);
    }

    private function ensureConfirmed(): void
    {
        unset($this->isConfirmed);

        if (! $this->isConfirmed) {
            throw ValidationException::withMessages(['security' => 'Bitte bestätige zuerst mit deinem Passkey oder melde dich neu an.']);
        }
    }

    private function user(): User
    {
        /** @var User */
        return Auth::user();
    }
}
