<?php

use Illuminate\Support\Facades\Route;
use Modules\Attendance\App\Http\Controllers\AttendanceController;

// Module routes are registered via loadRoutesFrom() and do NOT inherit
// the root web group automatically — always declare the group here.
Route::middleware('web')->group(function (): void {
    // Mockup phase: static sample data, nothing persisted.
    Route::middleware(['auth', 'module:attendance'])
        ->prefix('absensi')
        ->name('attendance.')
        ->controller(AttendanceController::class)
        ->group(function (): void {
            Route::get('/', 'overview')->name('overview');
            Route::get('input', 'input')->name('input');
            Route::get('rekap', 'monthly')->name('monthly');
        });
});
