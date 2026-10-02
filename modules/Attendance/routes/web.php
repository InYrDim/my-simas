<?php

use Illuminate\Support\Facades\Route;
use Modules\Attendance\App\Http\Controllers\DailyInputController;
use Modules\Attendance\App\Http\Controllers\LessonAttendanceController;
use Modules\Attendance\App\Http\Controllers\MonthlyRecapController;
use Modules\Attendance\App\Http\Controllers\OverviewController;
use Modules\Attendance\App\Http\Controllers\ScanController;
use Modules\Attendance\App\Http\Controllers\SettingsController;
use Modules\Attendance\App\Http\Controllers\StudentQrController;

// Module routes are registered via loadRoutesFrom() and do NOT inherit
// the root web group automatically — always declare the group here.
Route::middleware('web')->group(function (): void {
    Route::middleware(['auth', 'module:attendance'])
        ->prefix('absensi')
        ->name('attendance.')
        ->group(function (): void {
            Route::middleware('can:attendance.view')->group(function (): void {
                Route::get('/', OverviewController::class)->name('overview');
                Route::get('rekap', MonthlyRecapController::class)->name('monthly');
            });

            Route::middleware('can:attendance.daily.record')->group(function (): void {
                Route::get('input', [DailyInputController::class, 'index'])->name('input');
                Route::put('input', [DailyInputController::class, 'update'])->name('input.update');
            });

            Route::middleware('can:attendance.lesson.use')->group(function (): void {
                Route::get('jam-pelajaran', [LessonAttendanceController::class, 'index'])->name('lessons');
                Route::put('jam-pelajaran', [LessonAttendanceController::class, 'update'])->name('lessons.update');
            });

            // The scanner serves the gate and the lessons: either one, while
            // the school has it on, opens the page; the mode of a scan decides
            // which one it needs (ScanController, ScanRequest).
            Route::get('pindai', [ScanController::class, 'index'])->name('scan');
            Route::post('pindai', [ScanController::class, 'store'])->name('scan.store');
            Route::get('pindai/siswa', [ScanController::class, 'students'])->name('scan.students');

            Route::middleware('can:attendance.settings.manage')->group(function (): void {
                Route::get('pengaturan', [SettingsController::class, 'show'])->name('settings');
                Route::put('pengaturan', [SettingsController::class, 'update'])->name('settings.update');
            });

            // A student's own QR; the controller also asks for an account
            // linked to an active student.
            Route::middleware('can:attendance.qr.use')->group(function (): void {
                Route::get('qr-saya', [StudentQrController::class, 'show'])->name('my-qr');
                Route::post('qr-saya/token', [StudentQrController::class, 'token'])->name('my-qr.token');
            });
        });
});
