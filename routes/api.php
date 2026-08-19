<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PurchaseOrderController;
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

    Route::post(
        '/quotations/{quotation}/purchase-order',
        [PurchaseOrderController::class, 'store']
    );

    Route::get(
        '/purchase-orders/{purchaseOrder}',
        [PurchaseOrderController::class, 'show']
    );

    Route::post(
        '/purchase-orders/{purchaseOrder}/confirm',
        [PurchaseOrderController::class, 'confirm']
    );

    Route::post(
        '/purchase-orders/{purchaseOrder}/process',
        [PurchaseOrderController::class, 'process']
    );

    Route::post(
        '/purchase-orders/{purchaseOrder}/ship',
        [PurchaseOrderController::class, 'ship']
    );

    Route::post(
        '/purchase-orders/{purchaseOrder}/deliver',
        [PurchaseOrderController::class, 'deliver']
    );

    Route::post(
        '/purchase-orders/{purchaseOrder}/complete',
        [PurchaseOrderController::class, 'complete']
    );

    Route::post(
        '/purchase-orders/{purchaseOrder}/cancel',
        [PurchaseOrderController::class, 'cancel']
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
