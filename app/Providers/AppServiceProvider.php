<?php

namespace App\Providers;

use App\Http\Responses\PasskeyLoginResponse;
use App\Models\User;
use App\Services\Plex\PlexClient;
use App\Services\Tmdb\TmdbClient;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Passkeys\Contracts\PasskeyLoginResponse as PasskeyLoginResponseContract;
use Laravel\Passkeys\Contracts\PasskeyUser;
use Laravel\Passkeys\Passkeys;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(TmdbClient::class, fn (): TmdbClient => new TmdbClient(
            token: config('services.tmdb.token'),
            baseUrl: config('services.tmdb.base_url'),
            language: config('filmlog.language'),
            region: config('filmlog.region'),
        ));

        $this->app->singleton(PlexClient::class, fn (): PlexClient => new PlexClient(
            url: config('services.plex.url'),
            token: config('services.plex.token'),
        ));

        $this->app->singleton(PasskeyLoginResponseContract::class, PasskeyLoginResponse::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Wie beim Magic Link: Nur das Admin-Konto darf sich anmelden.
        Passkeys::authorizeLoginUsing(fn (Request $request, PasskeyUser $user): bool => $user instanceof User
            && Str::lower($user->email) === Str::lower((string) config('filmlog.admin_email')));
    }
}
