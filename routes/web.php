<?php

use App\Http\Controllers\Auth\LoginLinkController;
use App\Http\Controllers\Auth\LogoutController;
use App\Livewire\Auth\Login;
use App\Livewire\Diary;
use App\Livewire\Lists;
use App\Livewire\ListShow;
use App\Livewire\MovieShow;
use App\Livewire\Review;
use App\Livewire\Search;
use App\Livewire\Settings;
use App\Livewire\Tonight;
use App\Livewire\TraktImportPage;
use App\Services\LoginLinks;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::livewire('/anmelden', Login::class)->name('login');
    Route::get('/anmelden/link/{token}', [LoginLinkController::class, 'show'])
        ->where('token', LoginLinks::TOKEN_PATTERN)
        ->name('login.link');
    Route::post('/anmelden/link/{token}', [LoginLinkController::class, 'store'])
        ->where('token', LoginLinks::TOKEN_PATTERN)
        ->middleware('throttle:10,1')
        ->name('login.link.confirm');
});

Route::middleware(['auth', 'login.fresh'])->group(function () {
    Route::livewire('/', Tonight::class)->name('tonight');
    Route::livewire('/suche', Search::class)->name('search');
    Route::livewire('/film/{tmdbId}', MovieShow::class)->whereNumber('tmdbId')->name('movie');
    Route::livewire('/listen', Lists::class)->name('lists');
    Route::livewire('/listen/{list}', ListShow::class)->whereNumber('list')->name('lists.show');
    Route::livewire('/tagebuch', Diary::class)->name('diary');
    Route::livewire('/rueckblick', Review::class)->name('review');
    Route::livewire('/einstellungen', Settings::class)->name('settings');
    Route::livewire('/einstellungen/trakt-import', TraktImportPage::class)->name('import');

    Route::post('/abmelden', LogoutController::class)->name('logout');
});
