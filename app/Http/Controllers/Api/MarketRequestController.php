<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BuyerRequest;
use App\Support\Paginated;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The supplier-facing market: published buyer requests open for quotations.
 *
 * Only supplier accounts (any status, so pending suppliers can see demand
 * while awaiting approval) may browse. Quoting itself requires approval.
 * Buyers are identified by business name only.
 */
class MarketRequestController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $supplier = $this->supplierOrAbort($request);

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'region' => ['nullable', 'string', 'max:100'],
            'category_id' => ['nullable', 'integer'],
            'quoted' => ['nullable', 'in:yes,no'],
        ]);

        $requests = BuyerRequest::query()
            ->openForQuotes()
            ->with([
                'buyerProfile:id,business_name',
                'items.product:id,name,unit,category_id',
                'quotations' => fn ($q) => $q->where('supplier_id', $supplier->id)->select('id', 'buyer_request_id', 'supplier_id', 'status', 'total_amount'),
            ])
            ->withCount('items')
            ->when($validated['search'] ?? null, fn (Builder $q, $term) => $q->where(fn (Builder $w) => $w
                ->where('title', 'like', Paginated::like($term))
                ->orWhere('request_number', 'like', Paginated::like($term))))
            ->when($validated['region'] ?? null, fn (Builder $q, $region) => $q->where('delivery_region', $region))
            ->when($validated['category_id'] ?? null, fn (Builder $q, $categoryId) => $q
                ->whereHas('items.product', fn (Builder $p) => $p->where('category_id', $categoryId)))
            ->when(($validated['quoted'] ?? null) === 'yes', fn (Builder $q) => $q
                ->whereHas('quotations', fn (Builder $w) => $w->where('supplier_id', $supplier->id)))
            ->when(($validated['quoted'] ?? null) === 'no', fn (Builder $q) => $q
                ->whereDoesntHave('quotations', fn (Builder $w) => $w->where('supplier_id', $supplier->id)))
            ->latest('published_at')
            ->latest('id')
            ->paginate(Paginated::perPage($request));

        return Paginated::json($requests, fn (BuyerRequest $r) => $this->present($r));
    }

    public function show(Request $request, BuyerRequest $buyerRequest): JsonResponse
    {
        $supplier = $this->supplierOrAbort($request);

        $myQuotation = $buyerRequest->quotations()
            ->where('supplier_id', $supplier->id)
            ->with('items.product:id,name,unit')
            ->first();

        // Closed/expired requests stay visible only to suppliers who quoted.
        abort_unless($buyerRequest->isOpenForQuotes() || $myQuotation, 404);

        $buyerRequest->load(['buyerProfile:id,business_name', 'items.product:id,name,unit,category_id']);
        $offeredProductIds = $supplier->supplierProducts()->where('is_active', true)->pluck('product_id');

        return response()->json([
            'buyer_request' => [
                ...$this->present($buyerRequest->setRelation('quotations', collect())),
                'is_open_for_quotes' => $buyerRequest->isOpenForQuotes(),
                'offered_product_ids' => $offeredProductIds,
            ],
            'my_quotation' => $myQuotation,
            'can_quote' => $supplier->status === 'approved' && $buyerRequest->isOpenForQuotes() && ! $myQuotation,
        ]);
    }

    private function present(BuyerRequest $request): array
    {
        $mine = $request->quotations->first();

        return [
            'id' => $request->id,
            'request_number' => $request->request_number,
            'title' => $request->title,
            'description' => $request->description,
            'delivery_region' => $request->delivery_region,
            'delivery_address' => $request->delivery_address,
            'needed_by' => $request->needed_by?->toDateString(),
            'status' => $request->status,
            'published_at' => $request->published_at?->toIso8601String(),
            'expires_at' => $request->expires_at?->toIso8601String(),
            'buyer' => ['business_name' => $request->buyerProfile?->business_name],
            'items' => $request->items->map(fn ($item) => [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product' => $item->product?->only(['id', 'name', 'unit']),
                'quantity' => $item->quantity,
                'unit' => $item->unit,
                'notes' => $item->notes,
            ])->values(),
            'items_count' => $request->items_count ?? $request->items->count(),
            'my_quotation' => $mine ? $mine->only(['id', 'status', 'total_amount']) : null,
        ];
    }

    private function supplierOrAbort(Request $request)
    {
        $supplier = $request->user()->supplier;

        abort_if($supplier === null, 403, 'Only supplier accounts can browse market requests.');

        return $supplier;
    }
}
