<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BuyerRequest;
use App\Models\PurchaseOrder;
use App\Models\SupplierInventory;
use App\Models\SupplierQuotation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Counts for the buyer and supplier dashboards, computed from live records.
 */
class WorkspaceSummaryController extends Controller
{
    private const ACTIVE_ORDER_STATUSES = ['pending', 'confirmed', 'processing', 'shipped', 'delivered'];

    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        $summary = [];

        if ($profile = $user->buyerProfile) {
            $requests = BuyerRequest::where('buyer_profile_id', $profile->id)
                ->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

            $summary['buyer'] = [
                'requests_by_status' => $requests,
                'quotations_awaiting_decision' => SupplierQuotation::where('status', 'submitted')
                    ->whereHas('buyerRequest', fn ($q) => $q->where('buyer_profile_id', $profile->id)->where('status', 'open'))
                    ->count(),
                'orders_in_progress' => PurchaseOrder::where('buyer_profile_id', $profile->id)
                    ->whereIn('status', self::ACTIVE_ORDER_STATUSES)->count(),
                'orders_awaiting_your_action' => PurchaseOrder::where('buyer_profile_id', $profile->id)
                    ->whereIn('status', ['shipped', 'delivered'])->count(),
            ];
        }

        if ($supplier = $user->supplier) {
            $summary['supplier'] = [
                'status' => $supplier->status,
                'open_market_requests' => BuyerRequest::openForQuotes()
                    ->whereDoesntHave('quotations', fn ($q) => $q->where('supplier_id', $supplier->id))
                    ->count(),
                'quotations_by_status' => SupplierQuotation::where('supplier_id', $supplier->id)
                    ->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
                'orders_to_fulfil' => PurchaseOrder::where('supplier_id', $supplier->id)
                    ->whereIn('status', ['pending', 'confirmed', 'processing'])->count(),
                'catalog_products' => $supplier->supplierProducts()->count(),
                'locations' => $supplier->locations()->count(),
                'low_stock_items' => SupplierInventory::query()
                    ->whereHas('supplierProduct', fn ($q) => $q->where('supplier_id', $supplier->id))
                    ->whereColumn('quantity', '<=', 'low_stock_threshold')
                    ->count(),
            ];
        }

        return response()->json(['summary' => $summary]);
    }
}
