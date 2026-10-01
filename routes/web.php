<?php

use App\Http\Controllers\EntryController;
use Illuminate\Support\Facades\Route;

/*
| The front door. The stock starter page used to live here; the shell now
| only dispatches by tenancy state and the landing page itself belongs to
| the Core module (route "home" -> /beranda).
*/
Route::get('/', EntryController::class);
