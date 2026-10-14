<?php

use App\Http\Controllers\Api\AdminBuyerController;
use App\Http\Controllers\Api\AdminCatalogController;
use App\Http\Controllers\Api\AdminDashboardController;
use App\Http\Controllers\Api\AdminMarketplaceController;
use App\Http\Controllers\Api\AdminProductProposalController;
use App\Http\Controllers\Api\AdminSupplierController;
use App\Http\Controllers\Api\AdminUserController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BuyerRequestController;
use App\Http\Controllers\Api\GoogleAuthController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\MarketRequestController;
use App\Http\Controllers\Api\OrderDocumentController;
use App\Http\Controllers\Api\PreferencesController;
use App\Http\Controllers\Api\ProductCatalogController;
use App\Http\Controllers\Api\ProductImageController;
use App\Http\Controllers\Api\ProductProposalController;
use App\Http\Controllers\Api\PurchaseOrderController;
use App\Http\Controllers\Api\SupplierInventoryController;
use App\Http\Controllers\Api\SupplierLocationController;
use App\Http\Controllers\Api\SupplierPriceController;
use App\Http\Controllers\Api\SupplierProductController;
use App\Http\Controllers\Api\SupplierQuotationController;
use App\Http\Controllers\Api\WorkspaceSummaryController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public
|--------------------------------------------------------------------------
*/

Route::get('/health', HealthController::class);

Route::middleware('throttle:auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
    Route::post('/auth/google/exchange', [GoogleAuthController::class, 'exchange']);
});

Route::get('/catalog/filters', [ProductCatalogController::class, 'filters']);
Route::get('/products', [ProductCatalogController::class, 'index']);
Route::get('/products/suggest', [ProductCatalogController::class, 'suggest'])->middleware('throttle:catalog-suggest');
Route::get('/products/{product}', [ProductCatalogController::class, 'show'])->whereNumber('product');

/*
|--------------------------------------------------------------------------
| Signed in (any role, account not suspended)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', 'active'])->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::put('/me', [AuthController::class, 'updateProfile']);
    Route::put('/me/password', [AuthController::class, 'updatePassword']);
    Route::put('/me/preferences', [PreferencesController::class, 'update']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/workspace/summary', WorkspaceSummaryController::class);

    // Buyer requests (buyers)
    Route::get('/buyer-requests', [BuyerRequestController::class, 'index']);
    Route::get('/buyer-requests/{buyerRequest}', [BuyerRequestController::class, 'show']);

    Route::middleware('throttle:marketplace-writes')->group(function () {
        Route::post('/buyer-requests', [BuyerRequestController::class, 'store']);
        Route::put('/buyer-requests/{buyerRequest}', [BuyerRequestController::class, 'update']);
        Route::delete('/buyer-requests/{buyerRequest}', [BuyerRequestController::class, 'destroy']);
        Route::post('/buyer-requests/{buyerRequest}/items', [BuyerRequestController::class, 'addItem']);
        Route::put('/buyer-requests/{buyerRequest}/items/{item}', [BuyerRequestController::class, 'updateItem']);
        Route::delete('/buyer-requests/{buyerRequest}/items/{item}', [BuyerRequestController::class, 'removeItem']);
        Route::post('/buyer-requests/{buyerRequest}/publish', [BuyerRequestController::class, 'publish']);
        Route::post('/buyer-requests/{buyerRequest}/cancel', [BuyerRequestController::class, 'cancel']);

        // Quotations (suppliers create/submit; buyers accept/decline)
        Route::post('/buyer-requests/{buyerRequest}/quotations', [SupplierQuotationController::class, 'store']);
        Route::put('/quotations/{quotation}', [SupplierQuotationController::class, 'update']);
        Route::post('/quotations/{quotation}/items', [SupplierQuotationController::class, 'addItem']);
        Route::delete('/quotations/{quotation}/items/{item}', [SupplierQuotationController::class, 'removeItem']);
        Route::post('/quotations/{quotation}/submit', [SupplierQuotationController::class, 'submit']);
        Route::post('/quotations/{quotation}/withdraw', [SupplierQuotationController::class, 'withdraw']);
        Route::post('/quotations/{quotation}/accept', [SupplierQuotationController::class, 'accept']);
        Route::post('/quotations/{quotation}/reject', [SupplierQuotationController::class, 'reject']);

        // Purchase orders
        Route::post('/quotations/{quotation}/purchase-order', [PurchaseOrderController::class, 'store']);
        Route::post('/purchase-orders/{purchaseOrder}/confirm', [PurchaseOrderController::class, 'confirm']);
        Route::post('/purchase-orders/{purchaseOrder}/process', [PurchaseOrderController::class, 'process']);
        Route::post('/purchase-orders/{purchaseOrder}/ship', [PurchaseOrderController::class, 'ship']);
        Route::post('/purchase-orders/{purchaseOrder}/deliver', [PurchaseOrderController::class, 'deliver']);
        Route::post('/purchase-orders/{purchaseOrder}/complete', [PurchaseOrderController::class, 'complete']);
        Route::post('/purchase-orders/{purchaseOrder}/cancel', [PurchaseOrderController::class, 'cancel']);
        Route::put('/purchase-orders/{purchaseOrder}/document/signatories', [OrderDocumentController::class, 'updateSignatories']);

        // Product proposals and listing photos (suppliers)
        Route::post('/product-proposals', [ProductProposalController::class, 'store']);
        Route::delete('/supplier-products/{supplierProduct}/images/{image}', [ProductImageController::class, 'destroyForListing'])->scopeBindings();
        Route::post('/supplier-products/{supplierProduct}/images/{image}/primary', [ProductImageController::class, 'primaryForListing'])->scopeBindings();
    });

    // Market feed (suppliers)
    Route::get('/market-requests', [MarketRequestController::class, 'index']);
    Route::get('/market-requests/{buyerRequest}', [MarketRequestController::class, 'show']);

    Route::get('/quotations', [SupplierQuotationController::class, 'index']);
    Route::get('/quotations/{quotation}', [SupplierQuotationController::class, 'show']);

    Route::get('/purchase-orders', [PurchaseOrderController::class, 'index']);
    Route::get('/purchase-orders/{purchaseOrder}', [PurchaseOrderController::class, 'show']);
    Route::get('/purchase-orders/{purchaseOrder}/document', [OrderDocumentController::class, 'show']);
    Route::get('/purchase-orders/{purchaseOrder}/document/pdf', [OrderDocumentController::class, 'download'])->middleware('throttle:documents');

    Route::get('/product-proposals', [ProductProposalController::class, 'index']);
    Route::post('/supplier-products/{supplierProduct}/images', [ProductImageController::class, 'storeForListing'])->middleware('throttle:uploads');

    // Supplier catalog, stock, prices and locations
    Route::apiResource('supplier-products', SupplierProductController::class);
    Route::apiResource('supplier-locations', SupplierLocationController::class);
    Route::apiResource('supplier-inventory', SupplierInventoryController::class)
        ->parameters(['supplier-inventory' => 'supplierInventory']);
    Route::apiResource('supplier-prices', SupplierPriceController::class);
});

/*
|--------------------------------------------------------------------------
| Administration
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', 'active', 'admin'])->prefix('admin')->group(function () {
    Route::get('/dashboard', [AdminDashboardController::class, 'index']);

    Route::get('/users', [AdminUserController::class, 'index']);
    Route::get('/users/{user}', [AdminUserController::class, 'show']);
    Route::patch('/users/{user}/role', [AdminUserController::class, 'updateRole']);
    Route::post('/users/{user}/suspend', [AdminUserController::class, 'suspend']);
    Route::post('/users/{user}/reactivate', [AdminUserController::class, 'reactivate']);
    Route::delete('/users/{user}', [AdminUserController::class, 'destroy']);

    Route::get('/suppliers', [AdminSupplierController::class, 'index']);
    Route::get('/suppliers/{supplier}', [AdminSupplierController::class, 'show']);
    Route::post('/suppliers/{supplier}/approve', [AdminSupplierController::class, 'approve']);
    Route::post('/suppliers/{supplier}/suspend', [AdminSupplierController::class, 'suspend']);
    Route::post('/suppliers/{supplier}/reactivate', [AdminSupplierController::class, 'reactivate']);

    Route::get('/buyers', [AdminBuyerController::class, 'index']);
    Route::get('/buyers/{buyer}', [AdminBuyerController::class, 'show']);
    Route::post('/buyers/{buyer}/suspend', [AdminBuyerController::class, 'suspend']);
    Route::post('/buyers/{buyer}/reactivate', [AdminBuyerController::class, 'reactivate']);

    Route::get('/catalog/products', [AdminCatalogController::class, 'products']);
    Route::post('/catalog/products', [AdminCatalogController::class, 'storeProduct']);
    Route::patch('/catalog/products/{product}', [AdminCatalogController::class, 'updateProduct']);
    Route::post('/catalog/products/{product}/images', [ProductImageController::class, 'storeForProduct'])->middleware('throttle:uploads');
    Route::post('/catalog/products/{product}/images/{image}/primary', [ProductImageController::class, 'primaryForProduct'])->scopeBindings();
    Route::delete('/catalog/products/{product}/images/{image}', [ProductImageController::class, 'destroyForProduct'])->scopeBindings();
    Route::get('/product-proposals', [AdminProductProposalController::class, 'index']);
    Route::post('/product-proposals/{proposal}/approve', [AdminProductProposalController::class, 'approve']);
    Route::post('/product-proposals/{proposal}/reject', [AdminProductProposalController::class, 'reject']);
    Route::get('/catalog/taxonomy', [AdminCatalogController::class, 'categories']);
    Route::post('/catalog/categories', [AdminCatalogController::class, 'storeCategory']);
    Route::patch('/catalog/categories/{category}', [AdminCatalogController::class, 'updateCategory']);
    Route::post('/catalog/brands', [AdminCatalogController::class, 'storeBrand']);
    Route::patch('/catalog/brands/{brand}', [AdminCatalogController::class, 'updateBrand']);

    Route::get('/buyer-requests', [AdminMarketplaceController::class, 'requests']);
    Route::get('/purchase-orders', [AdminMarketplaceController::class, 'orders']);
    Route::get('/audit-logs', [AdminMarketplaceController::class, 'auditLogs']);
});
