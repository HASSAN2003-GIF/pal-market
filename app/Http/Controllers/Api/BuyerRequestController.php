<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BuyerRequest;
use App\Models\BuyerRequestItem;
use App\Services\BuyerRequestService;
use App\Services\BuyerRequestStatusService;
use App\Support\Paginated;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BuyerRequestController extends Controller
{
    public function __construct(
        private BuyerRequestService $buyerRequestService,
        private BuyerRequestStatusService $buyerRequestStatusService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', BuyerRequest::class);

        $validated = $request->validate([
            'status' => ['nullable', Rule::in(BuyerRequest::STATUSES)],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $buyerRequests = $request->user()->buyerProfile
            ->requests()
            ->with('items.product')
            ->withCount([
                'quotations as quotations_count' => fn (Builder $q) => $q->where('status', '!=', 'draft'),
            ])
            ->when($validated['status'] ?? null, fn (Builder $q, $status) => $q->where('status', $status))
            ->when($validated['search'] ?? null, fn (Builder $q, $term) => $q->where(fn (Builder $w) => $w
                ->where('title', 'like', Paginated::like($term))
                ->orWhere('request_number', 'like', Paginated::like($term))))
            ->latest()
            ->latest('id')
            ->paginate(Paginated::perPage($request));

        return response()->json([
            'buyer_requests' => $buyerRequests->items(),
            'meta' => [
                'current_page' => $buyerRequests->currentPage(),
                'last_page' => $buyerRequests->lastPage(),
                'per_page' => $buyerRequests->perPage(),
                'total' => $buyerRequests->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', BuyerRequest::class);

        $validated = $request->validate([
            ...$this->detailRules(),
            'items' => ['sometimes', 'array', 'max:50'],
            'items.*.product_id' => ['required', 'integer', 'distinct', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'items.*.unit' => ['nullable', 'string', 'max:50'],
            'items.*.notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $buyerRequest = $this->buyerRequestService->create(
            $request->user()->activeBuyerProfile()->id,
            $validated['title'],
            $validated['description'] ?? null,
            $validated,
        );

        return response()->json([
            'message' => 'Buyer request created successfully.',
            'buyer_request' => $buyerRequest->load(['buyerProfile', 'items.product']),
        ], 201);
    }

    public function show(BuyerRequest $buyerRequest): JsonResponse
    {
        $this->authorize('view', $buyerRequest);

        $buyerRequest->load([
            'buyerProfile',
            'items.product',
            'visibleQuotations' => fn ($q) => $q
                ->with(['supplier:id,business_name,status', 'items.product:id,name,unit', 'purchaseOrder:id,supplier_quotation_id,order_number,status'])
                ->orderBy('total_amount'),
        ]);

        $data = $buyerRequest->toArray();
        // Expose the buyer-visible list under the established "quotations" key.
        $data['quotations'] = $data['visible_quotations'];
        unset($data['visible_quotations']);

        return response()->json(['buyer_request' => $data]);
    }

    public function update(Request $request, BuyerRequest $buyerRequest): JsonResponse
    {
        $this->authorize('update', $buyerRequest);

        $validated = $request->validate($this->detailRules());

        $buyerRequest = $this->buyerRequestService->update(
            $buyerRequest,
            $validated['title'],
            $validated['description'] ?? null,
            $validated,
        );

        return response()->json([
            'message' => 'Buyer request updated successfully.',
            'buyer_request' => $buyerRequest->load(['buyerProfile', 'items.product']),
        ]);
    }

    public function destroy(BuyerRequest $buyerRequest): JsonResponse
    {
        $this->authorize('delete', $buyerRequest);

        $this->buyerRequestService->delete($buyerRequest);

        return response()->json(['message' => 'Draft request deleted.']);
    }

    public function addItem(Request $request, BuyerRequest $buyerRequest): JsonResponse
    {
        $this->authorize('update', $buyerRequest);

        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'unit' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $item = $this->buyerRequestService->addItem(
            $buyerRequest,
            $validated['product_id'],
            $validated['quantity'],
            $validated['unit'] ?? null,
            $validated['notes'] ?? null
        );

        return response()->json([
            'message' => 'Buyer request item added successfully.',
            'item' => $item->load('product'),
            'buyer_request' => $buyerRequest->fresh()->load('items.product'),
        ], 201);
    }

    public function updateItem(Request $request, BuyerRequest $buyerRequest, BuyerRequestItem $item): JsonResponse
    {
        $this->authorize('update', $buyerRequest);
        abort_unless($item->buyer_request_id === $buyerRequest->id, 404);

        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'unit' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $item = $this->buyerRequestService->updateItem(
            $item->setRelation('buyerRequest', $buyerRequest),
            $validated['quantity'],
            $validated['unit'] ?? null,
            $validated['notes'] ?? null,
        );

        return response()->json([
            'message' => 'Item updated.',
            'item' => $item->load('product'),
        ]);
    }

    public function removeItem(BuyerRequest $buyerRequest, BuyerRequestItem $item): JsonResponse
    {
        $this->authorize('update', $buyerRequest);
        abort_unless($item->buyer_request_id === $buyerRequest->id, 404);

        $this->buyerRequestService->removeItem($item->setRelation('buyerRequest', $buyerRequest));

        return response()->json([
            'message' => 'Item removed.',
            'buyer_request' => $buyerRequest->fresh()->load('items.product'),
        ]);
    }

    public function publish(Request $request, BuyerRequest $buyerRequest): JsonResponse
    {
        $this->authorize('publish', $buyerRequest);

        $validated = $request->validate([
            'open_for_days' => ['nullable', 'integer', 'min:1', 'max:'.BuyerRequestStatusService::MAX_OPEN_DAYS],
        ]);

        $buyerRequest = $this->buyerRequestStatusService->publish(
            $buyerRequest,
            $validated['open_for_days'] ?? null,
        );

        return response()->json([
            'message' => 'Buyer request published successfully.',
            'buyer_request' => $buyerRequest->load(['buyerProfile', 'items.product']),
        ]);
    }

    public function cancel(BuyerRequest $buyerRequest): JsonResponse
    {
        $this->authorize('cancel', $buyerRequest);

        $buyerRequest = $this->buyerRequestStatusService->cancel($buyerRequest);

        return response()->json([
            'message' => 'Buyer request cancelled successfully.',
            'buyer_request' => $buyerRequest->load(['buyerProfile', 'items.product']),
        ]);
    }

    private function detailRules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'delivery_region' => ['nullable', 'string', 'max:100'],
            'delivery_address' => ['nullable', 'string', 'max:500'],
            'needed_by' => ['nullable', 'date', 'after_or_equal:today'],
        ];
    }
}
