<?php

namespace App\Providers;

use App\Services\Plex\PlexClient;
use App\Services\Tmdb\TmdbClient;
use Illuminate\Support\ServiceProvider;

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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
