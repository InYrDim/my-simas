<?php

use Illuminate\Support\Facades\Route;
use Modules\Identity\App\Http\Controllers\Auth\AuthenticatedSessionController;
use Modules\Identity\App\Http\Controllers\Auth\NewPasswordController;
use Modules\Identity\App\Http\Controllers\Auth\PasswordResetLinkController;

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

        // Password reset (Fase 2, tenant-scoped tokens). Same pattern:
        // rate limiting lives in the controller with the tenant id in
        // the key. Central hosts never serve these (no tenant context).
        Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])
            ->name('password.request');

        Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
            ->name('password.email');

        Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])
            ->name('password.reset');

        Route::post('reset-password', [NewPasswordController::class, 'store'])
            ->name('password.update');
    });

    Route::middleware('auth')->group(function (): void {
        Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
            ->name('logout');
    });
});
