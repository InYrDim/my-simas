<?php

use Illuminate\Support\Facades\Route;
use Modules\Platform\App\Http\Controllers\ApplicationReviewController;
use Modules\Platform\App\Http\Controllers\Auth\ProviderAuthenticatedSessionController;
use Modules\Platform\App\Http\Controllers\BillingController;
use Modules\Platform\App\Http\Controllers\InvoiceController;
use Modules\Platform\App\Http\Controllers\PlanController;
use Modules\Platform\App\Http\Controllers\ProviderHomeController;
use Modules\Platform\App\Http\Controllers\SchoolApplyController;
use Modules\Platform\App\Http\Controllers\TenantConsoleController;
use Modules\Platform\App\Http\Controllers\TenantSubscriptionController;

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

        // UI-first stage: read-only pages over mock data (no permissions yet).
        Route::get('tenants', [TenantConsoleController::class, 'index'])
            ->name('tenants.index');

        Route::get('tenants/{tenant}', [TenantConsoleController::class, 'show'])
            ->name('tenants.show');

        Route::put('tenants/{tenant}', [TenantConsoleController::class, 'update'])
            ->name('tenants.update');

        Route::post('tenants/{tenant}/suspend', [TenantConsoleController::class, 'suspend'])
            ->name('tenants.suspend');

        Route::post('tenants/{tenant}/activate', [TenantConsoleController::class, 'activate'])
            ->name('tenants.activate');

        Route::put('tenants/{tenant}/modules', [TenantConsoleController::class, 'syncModules'])
            ->name('tenants.modules');

        Route::prefix('tenants/{tenant}/subscription')->name('tenants.subscription.')->group(function (): void {
            Route::post('trial', [TenantSubscriptionController::class, 'startTrial'])->name('trial');
            Route::post('trial/extend', [TenantSubscriptionController::class, 'extendTrial'])->name('trial.extend');
            Route::post('activate', [TenantSubscriptionController::class, 'activate'])->name('activate');
            Route::put('plan', [TenantSubscriptionController::class, 'changePlan'])->name('plan');
            Route::put('cycle', [TenantSubscriptionController::class, 'changeCycle'])->name('cycle');
            Route::post('renew', [TenantSubscriptionController::class, 'renew'])->name('renew');
            Route::post('cancel', [TenantSubscriptionController::class, 'cancel'])->name('cancel');
        });

        Route::prefix('billing')->name('billing.')->group(function (): void {
            Route::get('/', [BillingController::class, 'index'])->name('index');
            Route::get('subscriptions', [BillingController::class, 'subscriptions'])->name('subscriptions');

            Route::get('plans', [PlanController::class, 'index'])->name('plans');
            Route::post('plans', [PlanController::class, 'store'])->name('plans.store');
            Route::put('plans/{plan}', [PlanController::class, 'update'])->name('plans.update');
            Route::post('plans/{plan}/archive', [PlanController::class, 'archive'])->name('plans.archive');
            Route::post('plans/{plan}/restore', [PlanController::class, 'restore'])->name('plans.restore');

            Route::get('invoices', [InvoiceController::class, 'index'])->name('invoices');
            Route::post('invoices/{invoice}/pay', [InvoiceController::class, 'pay'])->name('invoices.pay');
            Route::post('invoices/{invoice}/void', [InvoiceController::class, 'void'])->name('invoices.void');
        });

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
