<?php

use Illuminate\Support\Facades\Route;
use Modules\Ppdb\App\Http\Controllers\Account\ApplicationController;
use Modules\Ppdb\App\Http\Controllers\Account\JoinSchoolController;
use Modules\Ppdb\App\Http\Controllers\Account\PasswordController;
use Modules\Ppdb\App\Http\Controllers\Account\PortalController;
use Modules\Ppdb\App\Http\Controllers\Account\RegisterController;
use Modules\Ppdb\App\Http\Controllers\Account\SessionController;
use Modules\Ppdb\App\Http\Controllers\Account\VerificationController as AccountVerificationController;
use Modules\Ppdb\App\Http\Controllers\AnswerFileController;
use Modules\Ppdb\App\Http\Controllers\ApplicantController;
use Modules\Ppdb\App\Http\Controllers\EnrollmentController;
use Modules\Ppdb\App\Http\Controllers\FormController;
use Modules\Ppdb\App\Http\Controllers\OverviewController;
use Modules\Ppdb\App\Http\Controllers\SelectionController;
use Modules\Ppdb\App\Http\Controllers\SettingsController;
use Modules\Ppdb\App\Http\Controllers\VerificationController;
use Modules\Ppdb\App\Http\Middleware\AuthenticatePpdbAccount;

// Module routes are registered via loadRoutesFrom() and do NOT inherit
// the root web group automatically — always declare the group here.
Route::middleware('web')->group(function (): void {
    // The applicant's own account (central pages, guard `ppdb`): never a
    // school user, so no tenant is needed to reach any of it. IP throttles
    // are safe here for that reason (the tenant-keyed pattern is for tenant
    // routes). Pages that touch a school enter it explicitly.
    Route::prefix('calon-siswa')->name('ppdb.account.')->group(function (): void {
        Route::get('daftar', [RegisterController::class, 'create'])->name('register');
        Route::post('daftar', [RegisterController::class, 'store'])
            ->middleware('throttle:5,10')
            ->name('register.store');

        Route::get('masuk', [SessionController::class, 'create'])->name('login');
        Route::post('masuk', [SessionController::class, 'store'])->name('login.attempt');

        Route::get('lupa-sandi', [PasswordController::class, 'request'])->name('password.request');
        Route::post('lupa-sandi', [PasswordController::class, 'email'])
            ->middleware('throttle:5,10')
            ->name('password.email');

        // Signed RELATIVE: the links are mailed from a queue worker that has
        // no request host. The POST carries the same signature.
        Route::middleware('signed:relative')->group(function (): void {
            Route::get('atur-ulang/{account}/{hash}', [PasswordController::class, 'reset'])
                ->whereNumber('account')
                ->name('password.reset');
            Route::post('atur-ulang/{account}/{hash}', [PasswordController::class, 'update'])
                ->whereNumber('account')
                ->name('password.update');
            Route::get('verifikasi/{account}/{hash}', [AccountVerificationController::class, 'verify'])
                ->whereNumber('account')
                ->name('verify');
        });

        Route::middleware(AuthenticatePpdbAccount::class)->group(function (): void {
            Route::post('keluar', [SessionController::class, 'destroy'])->name('logout');
            Route::get('verifikasi', [AccountVerificationController::class, 'notice'])->name('verify.notice');
            Route::post('verifikasi/kirim-ulang', [AccountVerificationController::class, 'resend'])
                ->middleware('throttle:3,10')
                ->name('verify.resend');
        });

        Route::middleware(AuthenticatePpdbAccount::class.':verified')->group(function (): void {
            Route::get('/', PortalController::class)->name('home');

            // Joining a school by its code, and leaving it again until the form is sent.
            Route::get('gabung', [JoinSchoolController::class, 'show'])->name('join');
            Route::post('gabung', [JoinSchoolController::class, 'store'])->name('join.store');
            Route::delete('gabung', [JoinSchoolController::class, 'destroy'])->name('join.leave');

            // The registration form of the school the account joined.
            Route::get('formulir', [ApplicationController::class, 'form'])->name('form');
            Route::post('formulir', [ApplicationController::class, 'store'])->name('form.store');
            Route::put('formulir', [ApplicationController::class, 'update'])->name('form.update');
            Route::get('formulir/berkas/{field}', [ApplicationController::class, 'file'])->whereNumber('field')->name('form.file');
        });
    });

    Route::middleware(['auth', 'module:ppdb', 'can:ppdb.view'])
        ->prefix('ppdb')
        ->name('ppdb.')
        ->group(function (): void {
            Route::get('/', OverviewController::class)->name('overview');

            Route::get('pendaftar', [ApplicantController::class, 'index'])->name('applicants');
            Route::get('pendaftar/{applicant}', [ApplicantController::class, 'show'])
                ->whereNumber('applicant')
                ->name('applicants.show');

            Route::get('pendaftar/{applicant}/berkas/{field}', AnswerFileController::class)
                ->whereNumber(['applicant', 'field'])
                ->name('applicants.file');

            Route::get('seleksi', [SelectionController::class, 'index'])->name('selection');

            Route::middleware('can:ppdb.selection.manage')->group(function (): void {
                Route::put('seleksi', [SelectionController::class, 'update'])->name('selection.update');
                Route::post('seleksi/umumkan', [SelectionController::class, 'publish'])->name('selection.publish');
            });

            Route::middleware('can:ppdb.applicants.manage')->group(function (): void {
                Route::get('pendaftar/tambah', [ApplicantController::class, 'create'])->name('applicants.create');
                Route::post('pendaftar', [ApplicantController::class, 'store'])->name('applicants.store');

                Route::where(['applicant' => '[0-9]+'])->group(function (): void {
                    Route::put('pendaftar/{applicant}', [ApplicantController::class, 'update'])->name('applicants.update');
                    Route::delete('pendaftar/{applicant}', [ApplicantController::class, 'destroy'])->name('applicants.cancel');
                    Route::put('pendaftar/{applicant}/verifikasi', [VerificationController::class, 'update'])->name('applicants.verify');
                    Route::post('pendaftar/{applicant}/daftar-ulang', [EnrollmentController::class, 'store'])->name('applicants.enroll');
                });
            });

            Route::middleware('can:ppdb.settings.manage')->controller(SettingsController::class)->group(function (): void {
                Route::get('pengaturan', 'show')->name('settings');
                Route::post('pengaturan/periode', 'storePeriod')->name('settings.periods.store');
                Route::put('pengaturan/periode/{period}', 'updatePeriod')->name('settings.periods.update');
                Route::post('pengaturan/periode/{period}/gelombang', 'storeWave')->name('settings.waves.store');
                Route::put('pengaturan/gelombang/{wave}', 'updateWave')->name('settings.waves.update');
                Route::delete('pengaturan/gelombang/{wave}', 'destroyWave')->name('settings.waves.destroy');
                Route::put('pengaturan/periode/{period}/jalur', 'updatePaths')->name('settings.paths.update');
                Route::delete('pengaturan/jalur/{path}', 'destroyPath')->name('settings.paths.destroy');
            });

            Route::middleware('can:ppdb.settings.manage')->controller(FormController::class)->group(function (): void {
                Route::get('formulir', 'show')->name('form');
                Route::put('formulir/{period}', 'update')->name('form.update');
                Route::delete('formulir/kolom/{field}', 'destroy')->name('form.fields.destroy');
            });
        });
});
