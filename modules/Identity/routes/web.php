<?php

use Illuminate\Support\Facades\Route;
use Modules\Identity\App\Http\Controllers\Auth\AuthenticatedSessionController;
use Modules\Identity\App\Http\Controllers\Auth\NewPasswordController;
use Modules\Identity\App\Http\Controllers\Auth\PasswordResetLinkController;
use Modules\Identity\App\Http\Controllers\Auth\SetPasswordController;

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

        // Set-password acceptance (Fase 2 Stage 8): the emailed link a
        // provisioned (password-null) account uses to activate. Same
        // host and token machinery as reset — the page decides the
        // effect. Rate limiting lives in the controller (tenant-keyed).
        Route::get('set-password', [SetPasswordController::class, 'create'])
            ->name('password.set');

        Route::post('set-password', [SetPasswordController::class, 'store'])
            ->name('password.set.accept');
    });

    Route::middleware('auth')->group(function (): void {
        Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
            ->name('logout');
    });
});
