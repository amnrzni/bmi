<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\SsoController;
use App\Http\Controllers\ConsentController;
use App\Livewire\Analytics;
use App\Livewire\Events;
use App\Livewire\Home;
use App\Livewire\MyProgress;
use App\Livewire\Roster;
use App\Livewire\RosterImport;
use App\Livewire\WeighInSession;
use Illuminate\Support\Facades\Route;

// ------------------------------------------------------------------ guest

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);

    Route::get('/auth/sso/redirect', [SsoController::class, 'redirect'])->name('sso.redirect');
    Route::get('/auth/sso/callback', [SsoController::class, 'callback'])->name('sso.callback');
    Route::get('/auth/sso/stub', [SsoController::class, 'stub'])->name('sso.stub');
});

Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
Route::get('/logout', [LoginController::class, 'destroy']);

// ------------------------------------------------------- authenticated

Route::middleware('auth')->group(function () {
    // The consent gate itself must sit outside the `consented` middleware,
    // or it would redirect to itself.
    Route::get('/consent', [ConsentController::class, 'show'])->name('consent.show');
    Route::post('/consent', [ConsentController::class, 'store'])->name('consent.store');
    Route::post('/withdraw', [ConsentController::class, 'withdraw'])->name('consent.withdraw');

    Route::middleware('consented')->group(function () {
        Route::get('/', Home::class)->name('home');
        Route::get('/me', MyProgress::class)->name('me');

        // Everyone sees events; the admin controls are guarded inside the
        // component rather than by route middleware, since staff use this page
        // too for RSVP and check-in.
        Route::get('/events', Events::class)->name('events');

        // Roster, batch entry and full analytics are admin-only.
        Route::middleware('admin')->group(function () {
            Route::get('/weigh-in', WeighInSession::class)->name('weigh-in');
            Route::get('/roster', Roster::class)->name('roster');
            Route::get('/roster/import', RosterImport::class)->name('roster.import');
            Route::get('/analytics', Analytics::class)->name('analytics');
        });
    });
});
