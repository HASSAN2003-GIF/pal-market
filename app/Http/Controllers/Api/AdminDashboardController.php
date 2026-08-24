<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BuyerRequest;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\SupplierQuotation;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class AdminDashboardController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'statistics' => [
                'users' => User::count(),
                'buyers' => User::where('role', 'buyer')->count(),
                'suppliers' => [
                    'total' => Supplier::count(),
                    'pending' => Supplier::where('status', 'pending')->count(),
                    'approved' => Supplier::where('status', 'approved')->count(),
                    'suspended' => Supplier::where('status', 'suspended')->count(),
                ],
                'products' => Product::count(),
                'buyer_requests' => BuyerRequest::count(),
                'quotations' => SupplierQuotation::count(),
                'purchase_orders' => PurchaseOrder::count(),
            ],
        ]);
    }
}
