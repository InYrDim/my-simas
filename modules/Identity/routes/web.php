<?php

use Illuminate\Support\Facades\Route;
use Modules\Identity\App\Http\Controllers\Auth\AuthenticatedSessionController;

// Module routes are registered via loadRoutesFrom() and do NOT inherit
// the root web group automatically — always declare the group here.
Route::middleware('web')->group(function (): void {
    Route::middleware('guest')->group(function (): void {
        Route::get('login', [AuthenticatedSessionController::class, 'create'])
            ->name('login');

        // No throttle: middleware — the controller enforces TENANT-keyed
        // rate limiting itself (middleware ordering cannot guarantee
        // the tenant context is set inside a limiter closure).
        Route::post('login', [AuthenticatedSessionController::class, 'store'])
            ->name('login.attempt');
    });

    Route::middleware('auth')->group(function (): void {
        Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
            ->name('logout');
    });
});
