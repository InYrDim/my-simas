<?php

use Illuminate\Support\Facades\Route;
use Modules\Platform\App\Http\Controllers\Applicant\ForgotPasswordController;
use Modules\Platform\App\Http\Controllers\Applicant\OnboardingController;
use Modules\Platform\App\Http\Controllers\Applicant\PasswordSetupController;
use Modules\Platform\App\Http\Controllers\Applicant\RegisterController;
use Modules\Platform\App\Http\Controllers\Applicant\SessionController;
use Modules\Platform\App\Http\Controllers\Applicant\VerificationController;
use Modules\Platform\App\Http\Controllers\ApplicantConsoleController;
use Modules\Platform\App\Http\Controllers\ApplicationReviewController;
use Modules\Platform\App\Http\Controllers\Auth\ProviderAuthenticatedSessionController;
use Modules\Platform\App\Http\Controllers\BillingController;
use Modules\Platform\App\Http\Controllers\InvoiceController;
use Modules\Platform\App\Http\Controllers\PlanController;
use Modules\Platform\App\Http\Controllers\ProviderHomeController;
use Modules\Platform\App\Http\Controllers\TenantConsoleController;
use Modules\Platform\App\Http\Controllers\TenantSubscriptionController;
use Modules\Platform\App\Http\Controllers\WhatsappInstanceController;
use Modules\Platform\App\Http\Middleware\AuthenticateApplicant;

// Applicant accounts (Fase 4): people applying to bring a school onto the
// platform. Central pages on the shared host, dedicated 'applicant' guard
// — never a school user. IP throttle is safe here: none of this needs a
// tenant context (the tenant-keyed throttle pattern is for TENANT routes,
// e.g. Identity's login).
Route::middleware('web')->name('applicant.')->group(function (): void {
    Route::get('daftar-sekolah', [RegisterController::class, 'create'])
        ->name('register');

    // 5 registrations per IP per 10 minutes.
    Route::post('daftar-sekolah', [RegisterController::class, 'store'])
        ->middleware('throttle:5,10')
        ->name('register.store');

    Route::prefix('pemohon')->group(function (): void {
        Route::get('masuk', [SessionController::class, 'create'])
            ->name('login');

        Route::post('masuk', [SessionController::class, 'store'])
            ->name('login.attempt');

        Route::get('lupa-sandi', [ForgotPasswordController::class, 'create'])
            ->name('password.request');

        Route::post('lupa-sandi', [ForgotPasswordController::class, 'store'])
            ->middleware('throttle:5,10')
            ->name('password.email');

        // Signed links from the invitation and reset mails. Signed
        // RELATIVE: invitations are issued on the console host, and the
        // link must work on this one. The POST carries the same signature.
        Route::middleware('signed:relative')->group(function (): void {
            Route::get('undangan/{applicant}/{hash}', [PasswordSetupController::class, 'show'])
                ->whereNumber('applicant')
                ->name('invitation');

            Route::post('undangan/{applicant}/{hash}', [PasswordSetupController::class, 'store'])
                ->whereNumber('applicant')
                ->name('invitation.accept');

            Route::get('atur-ulang/{applicant}/{hash}', [PasswordSetupController::class, 'show'])
                ->whereNumber('applicant')
                ->name('password.reset');

            Route::post('atur-ulang/{applicant}/{hash}', [PasswordSetupController::class, 'store'])
                ->whereNumber('applicant')
                ->name('password.update');
        });

        // Signed link from the verification mail; opens in any browser.
        Route::get('verifikasi/{applicant}/{hash}', [VerificationController::class, 'verify'])
            ->whereNumber('applicant')
            ->middleware('signed')
            ->name('verify');

        Route::middleware(AuthenticateApplicant::class)->group(function (): void {
            Route::post('keluar', [SessionController::class, 'destroy'])
                ->name('logout');

            Route::get('verifikasi', [VerificationController::class, 'notice'])
                ->name('verify.notice');

            Route::post('verifikasi/kirim-ulang', [VerificationController::class, 'resend'])
                ->middleware('throttle:3,10')
                ->name('verify.resend');
        });

        Route::middleware(AuthenticateApplicant::class.':verified')->group(function (): void {
            Route::get('/', [OnboardingController::class, 'show'])
                ->name('home');

            Route::post('pengajuan', [OnboardingController::class, 'store'])
                ->middleware('throttle:10,10')
                ->name('application.store');

            // Correct a rejected application and send it back to review.
            Route::put('pengajuan', [OnboardingController::class, 'update'])
                ->middleware('throttle:10,10')
                ->name('application.update');
        });
    });
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

        // Applicant accounts (Fase 4): list and invite.
        Route::get('applicants', [ApplicantConsoleController::class, 'index'])
            ->name('applicants.index');

        Route::post('applicants', [ApplicantConsoleController::class, 'store'])
            ->name('applicants.store');

        Route::post('applicants/{applicant}/invite', [ApplicantConsoleController::class, 'resend'])
            ->whereNumber('applicant')
            ->name('applicants.invite');

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

        // WhatsApp per school (Fase 9): the schools' requests, the
        // decision, switching a school off and on again, and the
        // "approve automatically" setting.
        Route::prefix('whatsapp')->name('whatsapp.')->group(function (): void {
            Route::get('/', [WhatsappInstanceController::class, 'index'])->name('index');
            Route::put('settings', [WhatsappInstanceController::class, 'updateSettings'])->name('settings');
            Route::post('{instance}/approve', [WhatsappInstanceController::class, 'approve'])->whereNumber('instance')->name('approve');
            Route::post('{instance}/reject', [WhatsappInstanceController::class, 'reject'])->whereNumber('instance')->name('reject');
            Route::post('{instance}/disable', [WhatsappInstanceController::class, 'disable'])->whereNumber('instance')->name('disable');
            Route::post('{instance}/enable', [WhatsappInstanceController::class, 'enable'])->whereNumber('instance')->name('enable');
        });
    });
});
