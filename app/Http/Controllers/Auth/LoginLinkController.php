<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureLoginIsFresh;
use App\Services\LoginLinks;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Der Link aus der Mail öffnet zuerst eine Bestätigungsseite (GET); erst der
 * Klick dort (POST) verbraucht das Token. So können Mail-Scanner, die Links
 * vorab aufrufen, den Einmal-Link nicht entwerten.
 */
class LoginLinkController extends Controller
{
    public function show(string $token, LoginLinks $loginLinks): View|RedirectResponse
    {
        if (! $loginLinks->isValid($token)) {
            return $this->invalidLink();
        }

        return view('auth.confirm-login', ['token' => $token]);
    }

    public function store(Request $request, string $token, LoginLinks $loginLinks): RedirectResponse
    {
        $user = $loginLinks->consume($token);

        if (! $user) {
            return $this->invalidLink();
        }

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put(EnsureLoginIsFresh::SESSION_KEY, now()->getTimestamp());

        return redirect()->intended(route('tonight'));
    }

    private function invalidLink(): RedirectResponse
    {
        return redirect()->route('login')
            ->with('status', 'Der Login-Link ist abgelaufen oder wurde schon benutzt. Fordere einfach einen neuen an.');
    }
}
