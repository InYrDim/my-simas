<?php

use Illuminate\Support\Facades\Route;

Route::prefix('api')->middleware('api')->group(function (): void {
    // Identity module API routes. Populated when the module gains API endpoints.
});
