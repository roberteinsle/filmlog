<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

/**
 * TOTP-Codes aus einer Authenticator-App plus Einmal-Wiederherstellungscodes.
 * Wiederherstellungscodes werden nur als SHA-256-Hash gespeichert.
 */
class TwoFactorAuthentication
{
    public const int RECOVERY_CODE_COUNT = 8;

    private const string LAST_TIMESTEP_PREFIX = 'two-factor-timestep:';

    public function __construct(private Google2FA $google2fa) {}

    public function generateSecret(): string
    {
        return $this->google2fa->generateSecretKey();
    }

    /**
     * otpauth://-Link zum Einrichten; öffnet auf dem Handy direkt die Authenticator-App.
     */
    public function setupUrl(User $user, string $secret): string
    {
        return $this->google2fa->getQRCodeUrl(config('app.name'), $user->email, $secret);
    }

    /**
     * Prüft einen Code gegen ein noch nicht gespeichertes Secret (Einrichtung).
     */
    public function verifySecret(string $secret, string $code): bool
    {
        $code = $this->normalize($code);

        return preg_match('/^\d{6}$/', $code) === 1 && $this->google2fa->verifyKey($secret, $code) === true;
    }

    /**
     * Prüft einen Code gegen das gespeicherte Secret. Ein Code gilt nur einmal:
     * Der zuletzt verwendete Zeitschritt wird gemerkt und danach abgelehnt.
     */
    public function verify(User $user, string $code): bool
    {
        $code = $this->normalize($code);

        if (! $user->hasTwoFactorEnabled() || preg_match('/^\d{6}$/', $code) !== 1) {
            return false;
        }

        $cacheKey = self::LAST_TIMESTEP_PREFIX.$user->id;
        $timestep = $this->google2fa->verifyKeyNewer($user->two_factor_secret, $code, (int) Cache::get($cacheKey, 0));

        if ($timestep === false) {
            return false;
        }

        Cache::put($cacheKey, $timestep, now()->addMinutes(5));

        return true;
    }

    /**
     * Erzeugt neue Wiederherstellungscodes, speichert ihre Hashes und gibt die Klartexte einmalig zurück.
     *
     * @return list<string>
     */
    public function regenerateRecoveryCodes(User $user): array
    {
        $codes = Collection::times(self::RECOVERY_CODE_COUNT, fn (): string => Str::lower(Str::random(5).'-'.Str::random(5)))->all();

        $user->forceFill([
            'two_factor_recovery_codes' => array_map($this->hashRecoveryCode(...), $codes),
        ])->save();

        return $codes;
    }

    /**
     * Verbraucht einen Wiederherstellungscode, falls er gültig ist.
     */
    public function useRecoveryCode(User $user, string $code): bool
    {
        $hashes = $user->two_factor_recovery_codes ?? [];
        $hash = $this->hashRecoveryCode($code);
        $index = array_search($hash, $hashes, true);

        if (! $user->hasTwoFactorEnabled() || $index === false) {
            return false;
        }

        unset($hashes[$index]);
        $user->forceFill(['two_factor_recovery_codes' => array_values($hashes)])->save();

        return true;
    }

    public function remainingRecoveryCodes(User $user): int
    {
        return count($user->two_factor_recovery_codes ?? []);
    }

    public function enable(User $user, string $secret): void
    {
        $user->forceFill(['two_factor_secret' => $secret, 'two_factor_confirmed_at' => now()])->save();
    }

    public function disable(User $user): void
    {
        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        Cache::forget(self::LAST_TIMESTEP_PREFIX.$user->id);
    }

    private function hashRecoveryCode(string $code): string
    {
        return hash('sha256', Str::lower(trim($code)));
    }

    private function normalize(string $code): string
    {
        return preg_replace('/\s+/', '', $code) ?? '';
    }
}
