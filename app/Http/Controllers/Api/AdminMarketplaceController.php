<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\BuyerRequest;
use App\Models\PurchaseOrder;
use App\Support\Paginated;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Read-only oversight of marketplace activity, plus the audit trail.
 */
class AdminMarketplaceController extends Controller
{
    public function requests(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(BuyerRequest::STATUSES)],
        ]);

        $requests = BuyerRequest::query()
            ->with('buyerProfile:id,business_name')
            ->withCount(['items', 'quotations as submitted_quotations_count' => fn (Builder $q) => $q->where('status', '!=', 'draft')])
            ->when($validated['search'] ?? null, fn (Builder $q, $term) => $q->where(fn (Builder $w) => $w
                ->where('title', 'like', Paginated::like($term))
                ->orWhere('request_number', 'like', Paginated::like($term))))
            ->when($validated['status'] ?? null, fn (Builder $q, $status) => $q->where('status', $status))
            ->latest('id')
            ->paginate(Paginated::perPage($request, 25, 100));

        return Paginated::json($requests);
    }

    public function orders(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:pending,confirmed,processing,shipped,delivered,completed,cancelled'],
        ]);

        $orders = PurchaseOrder::query()
            ->with(['buyerProfile:id,business_name', 'supplier:id,business_name'])
            ->withCount('items')
            ->when($validated['search'] ?? null, fn (Builder $q, $term) => $q->where('order_number', 'like', Paginated::like($term)))
            ->when($validated['status'] ?? null, fn (Builder $q, $status) => $q->where('status', $status))
            ->latest('id')
            ->paginate(Paginated::perPage($request, 25, 100));

        return Paginated::json($orders);
    }

    public function auditLogs(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'action' => ['nullable', 'string', 'max:100'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $logs = AuditLog::query()
            ->when($validated['action'] ?? null, fn (Builder $q, $action) => $q->where('action', 'like', addcslashes($action, '%_\\').'%'))
            ->when($validated['search'] ?? null, fn (Builder $q, $term) => $q->where(fn (Builder $w) => $w
                ->where('description', 'like', Paginated::like($term))
                ->orWhere('actor_label', 'like', Paginated::like($term))))
            ->latest('id')
            ->paginate(Paginated::perPage($request, 30, 100));

        return Paginated::json($logs);
    }
}
