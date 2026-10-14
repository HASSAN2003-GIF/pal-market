<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\BuyerRequest;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\SupplierQuotation;
use App\Models\User;
use Illuminate\Http\JsonResponse;

/**
 * Operational overview. Every figure is a live count from the database:
 *  - completed_order_value: sum of total_amount of purchase orders in
 *    "completed" status, per currency (the marketplace does not process
 *    payments, so this is agreed order value, not money collected).
 */
class AdminDashboardController extends Controller
{
    public function index(): JsonResponse
    {
        $countBy = fn (string $model) => $model::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return response()->json([
            'statistics' => [
                'users' => User::count(),
                'buyers' => User::where('role', 'buyer')->count(),
                'admins' => User::where('role', 'admin')->count(),
                'suspended_users' => User::whereNotNull('suspended_at')->count(),
                'new_users_last_30_days' => User::where('created_at', '>=', now()->subDays(30))->count(),
                'suppliers' => [
                    'total' => Supplier::count(),
                    'pending' => Supplier::where('status', 'pending')->count(),
                    'approved' => Supplier::where('status', 'approved')->count(),
                    'suspended' => Supplier::where('status', 'suspended')->count(),
                ],
                'products' => Product::count(),
                'active_products' => Product::where('is_active', true)->count(),
                'buyer_requests' => BuyerRequest::count(),
                'buyer_requests_by_status' => $countBy(BuyerRequest::class),
                'quotations' => SupplierQuotation::count(),
                'quotations_by_status' => $countBy(SupplierQuotation::class),
                'purchase_orders' => PurchaseOrder::count(),
                'purchase_orders_by_status' => $countBy(PurchaseOrder::class),
                'completed_order_value' => PurchaseOrder::where('status', 'completed')
                    ->selectRaw('currency, sum(total_amount) as total')
                    ->groupBy('currency')
                    ->pluck('total', 'currency'),
            ],
            'attention' => [
                'suppliers_awaiting_approval' => Supplier::where('status', 'pending')->count(),
                'orders_pending_confirmation_over_48h' => PurchaseOrder::where('status', 'pending')
                    ->where('created_at', '<=', now()->subHours(48))->count(),
            ],
            'recent_activity' => AuditLog::query()
                ->latest('id')
                ->limit(8)
                ->get(['id', 'actor_label', 'action', 'description', 'created_at']),
        ]);
    }
}
