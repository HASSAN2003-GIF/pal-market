<?php

use App\Http\Controllers\Api\GoogleAuthController;
use Illuminate\Support\Facades\Route;

Route::get('/auth/google/redirect', [
    GoogleAuthController::class,
    'redirect',
]);

Route::get('/auth/google/callback', [
    GoogleAuthController::class,
    'callback',
]);

Route::get('/{any}', function () {
    return view('welcome');
})->where('any', '^(?!api|auth/google/(redirect|callback)$).*$');