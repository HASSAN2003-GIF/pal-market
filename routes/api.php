<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PurchaseOrderController;
use App\Http\Controllers\Api\SupplierQuotationController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\BuyerRequestController;
use App\Http\Controllers\Api\SupplierProductController;
use App\Http\Controllers\Api\SupplierLocationController;

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

    Route::get('/buyer-requests', [
    BuyerRequestController::class,
    'index',
]);

Route::post('/buyer-requests', [
    BuyerRequestController::class,
    'store',
]);

Route::get('/buyer-requests/{buyerRequest}', [
    BuyerRequestController::class,
    'show',
]);

Route::put('/buyer-requests/{buyerRequest}', [
    BuyerRequestController::class,
    'update',
]);

Route::post('/buyer-requests/{buyerRequest}/items', [
    BuyerRequestController::class,
    'addItem',
]);

Route::post('/buyer-requests/{buyerRequest}/publish', [
    BuyerRequestController::class,
    'publish',
]);

Route::post('/buyer-requests/{buyerRequest}/cancel', [
    BuyerRequestController::class,
    'cancel',
]);

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

    Route::get('/supplier-products', [SupplierProductController::class, 'index']);
    Route::post('/supplier-products', [SupplierProductController::class, 'store']);
    Route::get('/supplier-products/{supplierProduct}', [SupplierProductController::class, 'show']);
    Route::put('/supplier-products/{supplierProduct}', [SupplierProductController::class, 'update']);
    Route::delete('/supplier-products/{supplierProduct}', [SupplierProductController::class, 'destroy']);

    Route::get('/supplier-locations', [
    SupplierLocationController::class,
    'index',
]);

Route::post('/supplier-locations', [
    SupplierLocationController::class,
    'store',
]);

Route::get('/supplier-locations/{supplierLocation}', [
    SupplierLocationController::class,
    'show',
]);

Route::put('/supplier-locations/{supplierLocation}', [
    SupplierLocationController::class,
    'update',
]);

Route::delete('/supplier-locations/{supplierLocation}', [
    SupplierLocationController::class,
    'destroy',
]);

});
