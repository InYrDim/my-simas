<?php

use Illuminate\Support\Facades\Route;
use Modules\Identity\App\Http\Controllers\Auth\AuthenticatedSessionController;
use Modules\Identity\App\Http\Controllers\Auth\NewPasswordController;
use Modules\Identity\App\Http\Controllers\Auth\PasswordResetLinkController;
use Modules\Identity\App\Http\Controllers\Auth\SetPasswordController;
use Modules\Identity\App\Http\Controllers\UsersManagementController;

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

        // School-admin user management (Fase 2 Stage 9): every action
        // is policy-gated (identity.users.* permissions, same-tenant
        // re-assert). Role dropdown options come from
        // TenantRoles::names() via config('roles') labels.
        Route::middleware('module:identity')->name('identity.users.')->prefix('users')->group(function (): void {
            Route::get('/', [UsersManagementController::class, 'index'])
                ->name('index');

            Route::get('create', [UsersManagementController::class, 'create'])
                ->name('create');

            Route::post('/', [UsersManagementController::class, 'store'])
                ->name('store');

            // Email invitation (Stage 10): same create permission —
            // the password-null path for accounts that activate via
            // the emailed set-password link (Stage 8 machinery).
            Route::get('invite', [UsersManagementController::class, 'invite'])
                ->name('invite');

            Route::post('invite', [UsersManagementController::class, 'storeInvite'])
                ->name('store-invite');

            Route::get('{userId}/edit', [UsersManagementController::class, 'edit'])
                ->whereNumber('userId')
                ->name('edit');

            Route::put('{userId}', [UsersManagementController::class, 'update'])
                ->whereNumber('userId')
                ->name('update');

            Route::patch('{userId}/deactivate', [UsersManagementController::class, 'deactivate'])
                ->whereNumber('userId')
                ->name('deactivate');

            Route::patch('{userId}/reactivate', [UsersManagementController::class, 'reactivate'])
                ->whereNumber('userId')
                ->name('reactivate');

            Route::post('{userId}/send-reset', [UsersManagementController::class, 'sendReset'])
                ->whereNumber('userId')
                ->name('send-reset');
        });
    });
});
