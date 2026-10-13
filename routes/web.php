<?php

use App\Http\Controllers\Api\GoogleAuthController;
use Illuminate\Support\Facades\Route;

// Google OAuth runs on web routes so the session can hold the OAuth state.
Route::middleware('throttle:auth')->group(function () {
    Route::get('/auth/google/redirect', [GoogleAuthController::class, 'redirect']);
    Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback']);
});

// Everything else is the React single-page app.
Route::view('/{any?}', 'welcome')->where('any', '^(?!api/|up$|auth/google/(redirect|callback)$).*$');
