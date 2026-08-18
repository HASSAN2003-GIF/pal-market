<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\SupplierQuotationController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::post(
        '/buyer-requests/{buyerRequest}/quotations',
        [SupplierQuotationController::class, 'store']
    );

    Route::get('/quotations/{quotation}', [
        SupplierQuotationController::class,
        'show',
    ]);

    Route::post(
        '/quotations/{quotation}/items',
        [SupplierQuotationController::class, 'addItem']
    );

    Route::post('/quotations/{quotation}/submit', [
        SupplierQuotationController::class,
        'submit',
    ]);

    Route::post('/quotations/{quotation}/accept', [
        SupplierQuotationController::class,
        'accept',
    ]);

});
