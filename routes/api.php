<?php

use App\Http\Controllers\Api\AdminBuyerController;
use App\Http\Controllers\Api\AdminDashboardController;
use App\Http\Controllers\Api\AdminSupplierController;
use App\Http\Controllers\Api\AdminUserController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BuyerRequestController;
use App\Http\Controllers\Api\ProductCatalogController;
use App\Http\Controllers\Api\PurchaseOrderController;
use App\Http\Controllers\Api\SupplierInventoryController;
use App\Http\Controllers\Api\SupplierLocationController;
use App\Http\Controllers\Api\SupplierPriceController;
use App\Http\Controllers\Api\SupplierProductController;
use App\Http\Controllers\Api\SupplierQuotationController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);

Route::post('/verify', [
    AuthController::class,
    'verify',
]);

Route::post('/verify/resend', [
    AuthController::class,
    'resendVerification',
]);

Route::post('/login', [AuthController::class, 'login']);
Route::get('/products', [
    ProductCatalogController::class,
    'index',
]);

Route::get('/products/{product}', [
    ProductCatalogController::class,
    'show',
]);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::post(
        '/buyer-requests/{buyerRequest}/quotations',
        [SupplierQuotationController::class, 'store']
    );

    Route::get('/supplier-inventory', [
        SupplierInventoryController::class,
        'index',
    ]);

    Route::post('/supplier-inventory', [
        SupplierInventoryController::class,
        'store',
    ]);

    Route::get('/supplier-inventory/{supplierInventory}', [
        SupplierInventoryController::class,
        'show',
    ]);

    Route::put('/supplier-inventory/{supplierInventory}', [
        SupplierInventoryController::class,
        'update',
    ]);

    Route::delete('/supplier-inventory/{supplierInventory}', [
        SupplierInventoryController::class,
        'destroy',
    ]);

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

    Route::apiResource(
        'supplier-prices',
        SupplierPriceController::class
    );

});

Route::middleware(['auth:sanctum', 'admin'])->group(function () {
    Route::get('/admin/dashboard', [
        AdminDashboardController::class,
        'index',
    ]);

    Route::get('/admin/users', [
        AdminUserController::class,
        'index',
    ]);

    Route::get('/admin/users/{user}', [
        AdminUserController::class,
        'show',
    ]);

    Route::patch('/admin/users/{user}/role', [
        AdminUserController::class,
        'updateRole',
    ]);

    Route::delete('/admin/users/{user}', [
        AdminUserController::class,
        'destroy',
    ]);

    Route::get('/admin/suppliers', [
        AdminSupplierController::class,
        'index',
    ]);

    Route::get('/admin/suppliers/{supplier}', [
        AdminSupplierController::class,
        'show',
    ]);

    Route::post('/admin/suppliers/{supplier}/approve', [
        AdminSupplierController::class,
        'approve',
    ]);

    Route::post('/admin/suppliers/{supplier}/suspend', [
        AdminSupplierController::class,
        'suspend',
    ]);

    Route::post('/admin/suppliers/{supplier}/reactivate', [
        AdminSupplierController::class,
        'reactivate',
    ]);

    Route::get('/admin/buyers', [
        AdminBuyerController::class,
        'index',
    ]);

    Route::get('/admin/buyers/{buyer}', [
        AdminBuyerController::class,
        'show',
    ]);

    Route::post('/admin/buyers/{buyer}/suspend', [
        AdminBuyerController::class,
        'suspend',
    ]);

    Route::post('/admin/buyers/{buyer}/reactivate', [
        AdminBuyerController::class,
        'reactivate',
    ]);
});
