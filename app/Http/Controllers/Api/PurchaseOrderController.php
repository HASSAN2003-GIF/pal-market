<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use App\Models\SupplierQuotation;
use App\Services\AuditLogger;
use App\Services\OrderDocumentService;
use App\Services\PurchaseOrderService;
use App\Services\PurchaseOrderStatusService;
use App\Support\Paginated;
use App\Support\ProductImages;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PurchaseOrderController extends Controller
{
    private const RELATIONS = [
        'buyerProfile:id,business_name',
        'supplier:id,business_name',
        'supplierQuotation:id,quotation_number,buyer_request_id,valid_until',
        'supplierQuotation.buyerRequest:id,request_number,title,delivery_region,delivery_address,needed_by',
        'items.product:id,name,name_sw,unit',
    ];

    public function __construct(
        private PurchaseOrderService $purchaseOrderService,
        private PurchaseOrderStatusService $purchaseOrderStatusService,
        private AuditLogger $audit,
        private OrderDocumentService $documents,
    ) {}

    /**
     * Orders where the signed-in user is the buyer or the supplier.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', PurchaseOrder::class);

        $validated = $request->validate([
            'status' => ['nullable', 'in:pending,confirmed,processing,shipped,delivered,completed,cancelled'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $user = $request->user();

        $orders = PurchaseOrder::query()
            ->where(fn (Builder $q) => $q
                ->when($user->buyerProfile, fn (Builder $w, $profile) => $w->orWhere('buyer_profile_id', $profile->id))
                ->when($user->supplier, fn (Builder $w, $supplier) => $w->orWhere('supplier_id', $supplier->id)))
            ->with(self::RELATIONS)
            ->withCount('items')
            ->when($validated['status'] ?? null, fn (Builder $q, $status) => $q->where('status', $status))
            ->when($validated['search'] ?? null, fn (Builder $q, $term) => $q->whereLike('order_number', Paginated::like($term)))
            ->latest()
            ->latest('id')
            ->paginate(Paginated::perPage($request));

        return Paginated::json($orders);
    }

    public function store(Request $request, SupplierQuotation $quotation): JsonResponse
    {
        $this->authorize('create', [PurchaseOrder::class, $quotation]);

        $purchaseOrder = $this->purchaseOrderService->createFromQuotation($quotation);

        $this->audit->record('purchase_order.created', "Raised {$purchaseOrder->order_number} from {$quotation->quotation_number}.", $purchaseOrder);

        return response()->json([
            'message' => __('Purchase order created successfully.'),
            'purchase_order' => $purchaseOrder->load(['buyerProfile', 'supplier', 'supplierQuotation', 'items.product']),
        ], 201);
    }

    public function show(PurchaseOrder $purchaseOrder): JsonResponse
    {
        $this->authorize('view', $purchaseOrder);

        $purchaseOrder->load(self::RELATIONS);
        $covers = ProductImages::covers($purchaseOrder->items->pluck('product_id'));
        $purchaseOrder->items->each(fn ($item) => $item->setAttribute('image', $covers[$item->product_id]['thumb'] ?? null));

        return response()->json([
            'purchase_order' => $purchaseOrder,
        ]);
    }

    public function confirm(PurchaseOrder $purchaseOrder): JsonResponse
    {
        return $this->transition($purchaseOrder, 'confirm', 'Purchase order confirmed successfully.');
    }

    public function process(PurchaseOrder $purchaseOrder): JsonResponse
    {
        return $this->transition($purchaseOrder, 'process', 'Purchase order processed successfully.');
    }

    public function ship(PurchaseOrder $purchaseOrder): JsonResponse
    {
        return $this->transition($purchaseOrder, 'ship', 'Purchase order shipped successfully.');
    }

    public function deliver(PurchaseOrder $purchaseOrder): JsonResponse
    {
        return $this->transition($purchaseOrder, 'deliver', 'Purchase order delivered successfully.');
    }

    public function complete(PurchaseOrder $purchaseOrder): JsonResponse
    {
        return $this->transition($purchaseOrder, 'complete', 'Purchase order completed successfully.');
    }

    public function cancel(PurchaseOrder $purchaseOrder): JsonResponse
    {
        return $this->transition($purchaseOrder, 'cancel', 'Purchase order cancelled successfully.');
    }

    private function transition(PurchaseOrder $purchaseOrder, string $action, string $message): JsonResponse
    {
        $this->authorize($action, $purchaseOrder);

        $from = $purchaseOrder->status;
        $purchaseOrder = $this->purchaseOrderStatusService->{$action}($purchaseOrder);

        $this->audit->record(
            "purchase_order.{$action}",
            "{$purchaseOrder->order_number}: {$from} → {$purchaseOrder->status}.",
            $purchaseOrder,
            ['from' => $from, 'to' => $purchaseOrder->status],
        );

        // The order document is issued when the supplier confirms the order.
        if ($action === 'confirm') {
            $this->documents->issue($purchaseOrder, 'confirmed', request()->user());
        }

        return response()->json([
            'message' => $message,
            'purchase_order' => $purchaseOrder->load(['buyerProfile', 'supplier', 'supplierQuotation', 'items.product']),
        ]);
    }
}
