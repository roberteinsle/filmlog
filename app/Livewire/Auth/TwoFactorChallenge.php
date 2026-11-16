<?php

namespace App\Livewire\Auth;

use App\Models\User;
use App\Services\LoginSession;
use App\Services\TwoFactorAuthentication;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Zweiter Schritt nach dem Magic Link: Code aus der Authenticator-App oder
 * ein Wiederherstellungscode.
 */
#[Layout('layouts::guest')]
#[Title('Anmelden')]
class TwoFactorChallenge extends Component
{
    public const string SESSION_KEY = 'login.two_factor';

    /**
     * So lange darf man sich nach dem Klick auf den Magic Link mit dem Code Zeit lassen.
     */
    private const int VALID_MINUTES = 10;

    /**
     * Fehlversuche pro Konto innerhalb von zehn Minuten.
     */
    private const int MAX_ATTEMPTS = 5;

    public string $code = '';

    public bool $usesRecoveryCode = false;

    /**
     * Merkt sich nach dem Magic Link, wer den zweiten Schritt noch abschließen muss.
     */
    public static function start(User $user): void
    {
        session()->put(self::SESSION_KEY, [
            'user_id' => $user->id,
            'expires_at' => now()->addMinutes(self::VALID_MINUTES)->getTimestamp(),
        ]);
    }

    public function mount(): void
    {
        if ($this->pendingUser() === null) {
            $this->redirectRoute('login');
        }
    }

    public function toggleRecoveryCode(): void
    {
        $this->usesRecoveryCode = ! $this->usesRecoveryCode;
        $this->reset('code');
        $this->resetErrorBag();
    }

    public function verify(TwoFactorAuthentication $twoFactor, LoginSession $loginSession): void
    {
        $user = $this->pendingUser();

        if ($user === null) {
            session()->flash('status', 'Die Anmeldung hat zu lange gedauert. Bitte fordere einen neuen Login-Link an.');
            $this->redirectRoute('login');

            return;
        }

        $throttleKey = 'two-factor|'.$user->id;

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_ATTEMPTS)) {
            throw ValidationException::withMessages([
                'code' => 'Zu viele Versuche. Bitte in '.ceil(RateLimiter::availableIn($throttleKey) / 60).' Minuten erneut versuchen.',
            ]);
        }

        $isValid = $this->usesRecoveryCode
            ? $twoFactor->useRecoveryCode($user, $this->code)
            : $twoFactor->verify($user, $this->code);

        if (! $isValid) {
            RateLimiter::hit($throttleKey, 600);
            $this->reset('code');

            throw ValidationException::withMessages([
                'code' => $this->usesRecoveryCode ? 'Der Wiederherstellungscode ist ungültig.' : 'Der Code ist ungültig.',
            ]);
        }

        RateLimiter::clear($throttleKey);
        session()->forget(self::SESSION_KEY);
        $loginSession->login($user);

        $this->redirectIntended(route('tonight'));
    }

    public function render(): View
    {
        return view('livewire.auth.two-factor-challenge');
    }

    private function pendingUser(): ?User
    {
        $pending = session(self::SESSION_KEY);

        if (! is_array($pending) || ($pending['expires_at'] ?? 0) < now()->getTimestamp()) {
            return null;
        }

        $user = User::find($pending['user_id'] ?? null);

        return $user?->hasTwoFactorEnabled() ? $user : null;
    }
}
