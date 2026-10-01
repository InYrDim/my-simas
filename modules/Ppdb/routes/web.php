<?php

use Illuminate\Support\Facades\Route;
use Modules\Ppdb\App\Http\Controllers\PpdbController;

// Module routes are registered via loadRoutesFrom() and do NOT inherit
// the root web group automatically — always declare the group here.
Route::middleware('web')->group(function (): void {
    // Mockup phase: static sample data, nothing persisted.
    Route::middleware(['auth', 'module:ppdb'])
        ->prefix('ppdb')
        ->name('ppdb.')
        ->controller(PpdbController::class)
        ->group(function (): void {
            Route::get('/', 'overview')->name('overview');
            Route::get('pendaftar', 'applicants')->name('applicants');
            Route::get('seleksi', 'selection')->name('selection');
        });
});
