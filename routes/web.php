<?php

use App\Http\Controllers\Auth\LoginLinkController;
use App\Http\Controllers\Auth\LogoutController;
use App\Livewire\Auth\Login;
use App\Livewire\Diary;
use App\Livewire\Lists;
use App\Livewire\Review;
use App\Livewire\Search;
use App\Livewire\Tonight;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::livewire('/anmelden', Login::class)->name('login');
    Route::get('/anmelden/link/{token}', [LoginLinkController::class, 'show'])->name('login.link');
    Route::post('/anmelden/link/{token}', [LoginLinkController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('login.link.confirm');
});

Route::middleware(['auth', 'login.fresh'])->group(function () {
    Route::livewire('/', Tonight::class)->name('tonight');
    Route::livewire('/suche', Search::class)->name('search');
    Route::livewire('/listen', Lists::class)->name('lists');
    Route::livewire('/tagebuch', Diary::class)->name('diary');
    Route::livewire('/rueckblick', Review::class)->name('review');

    Route::post('/abmelden', LogoutController::class)->name('logout');
});
