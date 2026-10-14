<?php

use App\Http\Controllers\Api\GoogleAuthController;
use App\Http\Controllers\MediaController;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

// Google OAuth runs on web routes so the session can hold the OAuth state.
Route::middleware('throttle:auth')->group(function () {
    Route::get('/auth/google/redirect', [GoogleAuthController::class, 'redirect']);
    Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback']);
});

// Product photos, served from the media disk on this origin.
Route::get('/media/products/{directory}/{variant}.{extension}', MediaController::class)
    ->where(['directory' => '[0-9a-f-]{36}', 'variant' => 'thumb|large', 'extension' => 'webp|jpg'])
    // Public, cacheable files: no session or cookies.
    ->withoutMiddleware([
        StartSession::class,
        ShareErrorsFromSession::class,
        AddQueuedCookiesToResponse::class,
        EncryptCookies::class,
        PreventRequestForgery::class,
    ])
    ->name('media.show');

// Everything else is the React single-page app.
Route::view('/{any?}', 'welcome')->where('any', '^(?!api/|up$|media/|auth/google/(redirect|callback)$).*$');
