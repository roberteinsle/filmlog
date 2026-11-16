<?php

namespace App\Services;

use App\Http\Middleware\EnsureLoginIsFresh;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Gemeinsamer Abschluss aller Login-Wege (Passkey, Magic Link + Code).
 */
class LoginSession
{
    public function login(User $user): void
    {
        Auth::guard('web')->login($user);
        session()->regenerate();

        $this->markAsStarted();
    }

    /**
     * Startet die 7-Tage-Frist und zählt den Login als frische Bestätigung,
     * damit Passkeys und Authenticator direkt danach verwaltet werden können.
     */
    public function markAsStarted(): void
    {
        session()->put(EnsureLoginIsFresh::SESSION_KEY, now()->getTimestamp());
        session()->passwordConfirmed();
    }

    /**
     * Ob die letzte Bestätigung (Login oder Passkey) noch innerhalb von auth.password_timeout liegt.
     */
    public function isRecentlyConfirmed(): bool
    {
        $confirmedAt = (int) session('auth.password_confirmed_at', 0);

        return now()->getTimestamp() - $confirmedAt <= (int) config('auth.password_timeout');
    }
}
