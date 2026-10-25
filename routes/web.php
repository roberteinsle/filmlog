<?php

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
});

Route::middleware('auth')->group(function () {
    Route::livewire('/', Tonight::class)->name('tonight');
    Route::livewire('/suche', Search::class)->name('search');
    Route::livewire('/listen', Lists::class)->name('lists');
    Route::livewire('/tagebuch', Diary::class)->name('diary');
    Route::livewire('/rueckblick', Review::class)->name('review');

    Route::post('/abmelden', LogoutController::class)->name('logout');
});
