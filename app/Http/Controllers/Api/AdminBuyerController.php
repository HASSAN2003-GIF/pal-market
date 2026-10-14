<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BuyerProfile;
use App\Services\AuditLogger;
use App\Support\Paginated;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminBuyerController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:active,suspended'],
        ]);

        $buyers = BuyerProfile::query()
            ->with('user:id,name,email,phone')
            ->withCount(['requests', 'purchaseOrders'])
            ->when($validated['search'] ?? null, fn (Builder $q, $term) => $q->where(fn (Builder $w) => $w
                ->whereLike('business_name', Paginated::like($term))
                ->orWhereHas('user', fn (Builder $u) => $u->whereLike('email', Paginated::like($term))
                    ->orWhereLike('name', Paginated::like($term)))))
            ->when($validated['status'] ?? null, fn (Builder $q, $status) => $q->where('status', $status))
            ->orderBy('id')
            ->paginate(Paginated::perPage($request, 25, 100));

        return response()->json([
            'buyers' => $buyers->items(),
            'meta' => [
                'current_page' => $buyers->currentPage(),
                'last_page' => $buyers->lastPage(),
                'per_page' => $buyers->perPage(),
                'total' => $buyers->total(),
            ],
        ]);
    }

    public function show(BuyerProfile $buyer): JsonResponse
    {
        $buyer->load('user:id,name,email,phone')->loadCount(['requests', 'purchaseOrders']);

        return response()->json(['buyer' => $buyer]);
    }

    public function suspend(BuyerProfile $buyer): JsonResponse
    {
        if ($buyer->status !== 'active') {
            return response()->json(['message' => __('Only active buyers can be suspended.')], 422);
        }

        $buyer->update(['status' => 'suspended']);
        $this->audit->record('buyer.suspended', "Suspended buyer {$buyer->business_name}.", $buyer);

        return response()->json([
            'message' => __('Buyer suspended successfully.'),
            'buyer' => $buyer->load('user:id,name,email'),
        ]);
    }

    public function reactivate(BuyerProfile $buyer): JsonResponse
    {
        if ($buyer->status !== 'suspended') {
            return response()->json(['message' => __('Only suspended buyers can be reactivated.')], 422);
        }

        $buyer->update(['status' => 'active']);
        $this->audit->record('buyer.reactivated', "Reactivated buyer {$buyer->business_name}.", $buyer);

        return response()->json([
            'message' => __('Buyer reactivated successfully.'),
            'buyer' => $buyer->load('user:id,name,email'),
        ]);
    }
}
