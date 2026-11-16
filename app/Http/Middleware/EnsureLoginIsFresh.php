<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Beendet einen Login spätestens nach filmlog.login_lifetime_days Tagen,
 * unabhängig davon, wie aktiv die Session ist.
 */
class EnsureLoginIsFresh
{
    public const string SESSION_KEY = 'auth.logged_in_at';

    public function handle(Request $request, Closure $next): Response
    {
        $loggedInAt = $request->session()->get(self::SESSION_KEY);
        $maxAgeSeconds = (int) config('filmlog.login_lifetime_days') * 86400;

        if (Auth::check() && (! is_int($loggedInAt) || now()->getTimestamp() - $loggedInAt > $maxAgeSeconds)) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->with('status', 'Deine Anmeldung ist abgelaufen. Bitte melde dich neu an.');
        }

        return $next($request);
    }
}
