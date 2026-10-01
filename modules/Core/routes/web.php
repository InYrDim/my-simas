<?php

use Illuminate\Support\Facades\Route;
use Modules\Core\App\Http\Controllers\BerandaController;

// Module routes are registered via loadRoutesFrom() and do NOT inherit
// the root web group automatically — always declare the group here.
Route::middleware('web')->group(function (): void {
    // The school's landing record. Named "home" so the auth redirect
    // (redirectUsersTo) lands here after login. auth only — core is
    // always-active, so there is no module flag to check.
    Route::middleware('auth')->group(function (): void {
        Route::get('beranda', BerandaController::class)->name('home');
    });
});
