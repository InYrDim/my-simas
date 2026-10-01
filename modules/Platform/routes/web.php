<?php

use Illuminate\Support\Facades\Route;
use Modules\Platform\App\Http\Controllers\ApplicationReviewController;
use Modules\Platform\App\Http\Controllers\Auth\ProviderAuthenticatedSessionController;
use Modules\Platform\App\Http\Controllers\ProviderHomeController;
use Modules\Platform\App\Http\Controllers\SchoolApplyController;

// Public school application (Fase 2 Stage 7): NO auth.
// IP throttle is safe here — the form needs no tenant context (the
// tenant-keyed throttle pattern is for TENANT routes, e.g. Identity's
// login). Rate: 5 submissions per IP per 10 minutes.
Route::middleware(['web', 'throttle:5,10'])->group(function (): void {
    Route::get('daftar-sekolah', [SchoolApplyController::class, 'create'])
        ->name('school.apply.create');

    Route::post('daftar-sekolah', [SchoolApplyController::class, 'store'])
        ->name('school.apply.store');
});

// Provider console: the console host only (config tenancy.console_domain),
// dedicated 'provider' guard. This file is loaded via loadRoutesFrom() so
// the middleware groups are declared explicitly here. It MUST register
// before the tenant routes: both define /login, and the domain-bound
// route has to win on the console host.
Route::domain((string) config('tenancy.console_domain'))->middleware('web')->name('platform.')->group(function (): void {
    Route::middleware('guest:provider')->group(function (): void {
        Route::get('login', [ProviderAuthenticatedSessionController::class, 'create'])
            ->name('login');

        Route::post('login', [ProviderAuthenticatedSessionController::class, 'store'])
            ->name('login.attempt');
    });

    Route::middleware('auth:provider')->group(function (): void {
        Route::get('dashboard', ProviderHomeController::class)
            ->name('home');

        Route::post('logout', [ProviderAuthenticatedSessionController::class, 'destroy'])
            ->name('logout');

        // School application review (Fase 2 Stage 6): the approve POST
        // carries provider corrections (school data final = form ACC).
        Route::get('applications', [ApplicationReviewController::class, 'index'])
            ->name('applications.index');

        Route::get('applications/{application}', [ApplicationReviewController::class, 'show'])
            ->whereNumber('application')
            ->name('applications.show');

        Route::post('applications/{application}/approve', [ApplicationReviewController::class, 'approve'])
            ->whereNumber('application')
            ->name('applications.approve');

        Route::post('applications/{application}/reject', [ApplicationReviewController::class, 'reject'])
            ->whereNumber('application')
            ->name('applications.reject');
    });
});
