<?php

use Illuminate\Support\Facades\Route;
use Modules\Platform\App\Http\Controllers\Auth\ProviderAuthenticatedSessionController;
use Modules\Platform\App\Http\Controllers\ProviderHomeController;

// Provider console: CENTRAL hosts only, dedicated 'provider' guard.
// This file is loaded via loadRoutesFrom() so the middleware groups
// are declared explicitly here.
Route::middleware(['web', 'central'])->prefix('platform')->name('platform.')->group(function (): void {
    Route::middleware('guest:provider')->group(function (): void {
        Route::get('login', [ProviderAuthenticatedSessionController::class, 'create'])
            ->name('login');

        Route::post('login', [ProviderAuthenticatedSessionController::class, 'store'])
            ->name('login.attempt');
    });

    Route::middleware('auth:provider')->group(function (): void {
        Route::get('/', ProviderHomeController::class)
            ->name('home');

        Route::post('logout', [ProviderAuthenticatedSessionController::class, 'destroy'])
            ->name('logout');
    });
});
