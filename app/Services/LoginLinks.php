<?php

namespace App\Services;

use App\Mail\LoginLinkMail;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Einmal verwendbare Login-Links. Im Cache liegt nur der SHA-256-Hash des
 * Tokens, der Klartext steht ausschließlich in der verschickten Mail.
 */
class LoginLinks
{
    private const string CACHE_PREFIX = 'login-link:';

    /**
     * Format der Tokens (Str::random(64)); wird auch als Routen-Constraint genutzt.
     */
    public const string TOKEN_PATTERN = '[A-Za-z0-9]{64}';

    /**
     * Verschickt einen Login-Link, falls die Adresse die des Admins ist.
     * Für alle anderen Adressen passiert bewusst nichts (keine Auskunft,
     * welche Adressen existieren).
     */
    public function send(string $email): void
    {
        if (! $this->isAdminEmail($email)) {
            return;
        }

        $token = Str::random(64);
        $minutes = (int) config('filmlog.login_link_minutes');

        Cache::put(self::CACHE_PREFIX.hash('sha256', $token), Str::lower($email), now()->addMinutes($minutes));

        Mail::to($email)->send(new LoginLinkMail(
            url: route('login.link', ['token' => $token]),
            validMinutes: $minutes,
        ));
    }

    /**
     * Prüft, ob ein Token noch gültig ist, ohne es zu verbrauchen.
     */
    public function isValid(string $token): bool
    {
        return $this->hasValidFormat($token) && Cache::has(self::CACHE_PREFIX.hash('sha256', $token));
    }

    /**
     * Verbraucht das Token und liefert das (ggf. neu angelegte) Admin-Konto.
     */
    public function consume(string $token): ?User
    {
        if (! $this->hasValidFormat($token)) {
            return null;
        }

        $email = Cache::pull(self::CACHE_PREFIX.hash('sha256', $token));

        if (! is_string($email) || ! $this->isAdminEmail($email)) {
            return null;
        }

        return User::firstOrCreate(
            ['email' => $email],
            ['name' => 'Admin', 'email_verified_at' => now()],
        );
    }

    private function hasValidFormat(string $token): bool
    {
        return preg_match('/^'.self::TOKEN_PATTERN.'$/', $token) === 1;
    }

    private function isAdminEmail(string $email): bool
    {
        return Str::lower(trim($email)) === Str::lower((string) config('filmlog.admin_email'));
    }
}
