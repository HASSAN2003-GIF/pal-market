<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BuyerRequest;
use App\Models\SupplierQuotation;
use App\Models\SupplierQuotationItem;
use App\Services\AuditLogger;
use App\Services\PurchaseOrderService;
use App\Services\SupplierQuotationService;
use App\Services\SupplierQuotationStatusService;
use App\Support\Paginated;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SupplierQuotationController extends Controller
{
    private const RELATIONS = [
        'buyerRequest:id,buyer_profile_id,request_number,title,status,delivery_region,needed_by,expires_at',
        'buyerRequest.buyerProfile:id,business_name',
        'supplier:id,business_name,status',
        'items.product:id,name,unit',
        'purchaseOrder:id,supplier_quotation_id,order_number,status',
    ];

    public function __construct(
        private SupplierQuotationService $quotationService,
        private SupplierQuotationStatusService $quotationStatusService,
        private PurchaseOrderService $purchaseOrderService,
        private AuditLogger $audit,
    ) {}

    /**
     * The signed-in supplier's quotations.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', SupplierQuotation::class);

        $validated = $request->validate([
            'status' => ['nullable', 'in:draft,submitted,accepted,rejected,expired,cancelled'],
        ]);

        $quotations = $request->user()->supplier
            ->quotations()
            ->with(self::RELATIONS)
            ->withCount('items')
            ->when($validated['status'] ?? null, fn (Builder $q, $status) => $q->where('status', $status))
            ->latest()
            ->latest('id')
            ->paginate(Paginated::perPage($request));

        return Paginated::json($quotations);
    }

    /**
     * Creates a quotation. With "items", the whole quotation is created (and
     * optionally submitted) atomically; without, an empty draft is created.
     */
    public function store(Request $request, BuyerRequest $buyerRequest): JsonResponse
    {
        $supplier = $request->user()->supplier;

        if (! $supplier) {
            return response()->json([
                'message' => __('You do not have a supplier profile.'),
            ], 403);
        }

        if ($supplier->status !== 'approved') {
            return response()->json([
                'message' => __('Unable to create quotation.'),
                'errors' => [
                    'supplier_id' => [
                        'Your supplier account must be approved before creating a quotation.',
                    ],
                ],
            ], 422);
        }

        $this->authorize('create', SupplierQuotation::class);

        $validated = $request->validate([
            'delivery_fee' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'valid_until' => ['nullable', 'date', 'after:now'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'submit' => ['sometimes', 'boolean'],
            'items' => ['sometimes', 'array', 'min:1', 'max:50'],
            'items.*.product_id' => ['required', 'integer', 'distinct'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'items.*.quantity' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'items.*.unit' => ['nullable', 'string', 'max:50'],
            'items.*.notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $quotation = isset($validated['items'])
            ? $this->quotationService->createWithItems($buyerRequest, $supplier, $validated)
            : $this->quotationService->create($buyerRequest, $supplier, $validated);

        return response()->json([
            'message' => $quotation->status === 'submitted'
                ? 'Quotation submitted to the buyer.'
                : 'Quotation created successfully.',
            'quotation' => $quotation->load(['buyerRequest', 'supplier', 'items.product']),
        ], 201);
    }

    public function show(Request $request, SupplierQuotation $quotation): JsonResponse
    {
        $this->authorize('view', $quotation);

        return response()->json([
            'quotation' => $quotation->load(self::RELATIONS),
        ]);
    }

    public function update(Request $request, SupplierQuotation $quotation): JsonResponse
    {
        $this->authorize('update', $quotation);

        $validated = $request->validate([
            'delivery_fee' => ['sometimes', 'numeric', 'min:0', 'max:9999999999'],
            'valid_until' => ['sometimes', 'nullable', 'date', 'after:now'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);

        $quotation = $this->quotationService->updateTerms($quotation, $validated);

        return response()->json([
            'message' => __('Quotation updated.'),
            'quotation' => $quotation->load(self::RELATIONS),
        ]);
    }

    public function addItem(Request $request, SupplierQuotation $quotation): JsonResponse
    {
        $this->authorize('update', $quotation);

        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'unit' => ['required', 'string', 'max:50'],
            'unit_price' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $item = $this->quotationService->addItem(
            $quotation,
            $validated['product_id'],
            $validated['quantity'],
            $validated['unit'],
            (float) $validated['unit_price'],
            $validated['notes'] ?? null
        );

        return response()->json([
            'message' => __('Quotation item added successfully.'),
            'item' => $item->load('product'),
            'quotation' => $quotation->fresh()->load('items'),
        ], 201);
    }

    public function removeItem(SupplierQuotation $quotation, SupplierQuotationItem $item): JsonResponse
    {
        $this->authorize('update', $quotation);
        abort_unless($item->supplier_quotation_id === $quotation->id, 404);

        $quotation = $this->quotationService->removeItem($item->setRelation('supplierQuotation', $quotation));

        return response()->json([
            'message' => __('Item removed from the quotation.'),
            'quotation' => $quotation->load(self::RELATIONS),
        ]);
    }

    public function submit(SupplierQuotation $quotation): JsonResponse
    {
        $this->authorize('submit', $quotation);

        $quotation = $this->quotationStatusService->submit($quotation);

        return response()->json([
            'message' => __('Quotation submitted successfully.'),
            'quotation' => $quotation->load(['buyerRequest', 'supplier', 'items']),
        ]);
    }

    public function withdraw(SupplierQuotation $quotation): JsonResponse
    {
        $this->authorize('withdraw', $quotation);

        $quotation = $this->quotationStatusService->withdraw($quotation);

        return response()->json([
            'message' => __('Quotation withdrawn.'),
            'quotation' => $quotation->load(self::RELATIONS),
        ]);
    }

    /**
     * Accepts a quotation. With "create_purchase_order", the purchase order is
     * raised in the same transaction so the buyer never ends up with an
     * accepted quotation and no order because of a second failed call.
     */
    public function accept(Request $request, SupplierQuotation $quotation): JsonResponse
    {
        $this->authorize('accept', $quotation);

        $validated = $request->validate(['create_purchase_order' => ['sometimes', 'boolean']]);

        [$quotation, $purchaseOrder] = DB::transaction(function () use ($quotation, $validated) {
            $accepted = $this->quotationStatusService->accept($quotation);
            $order = ($validated['create_purchase_order'] ?? false)
                ? $this->purchaseOrderService->createFromQuotation($accepted)
                : null;

            return [$accepted, $order];
        });

        $this->audit->record(
            'quotation.accepted',
            "Accepted quotation {$quotation->quotation_number}".($purchaseOrder ? " and raised {$purchaseOrder->order_number}." : '.'),
            $quotation,
        );

        if ($purchaseOrder) {
            $this->audit->record('purchase_order.created', "Raised {$purchaseOrder->order_number} from {$quotation->quotation_number}.", $purchaseOrder);
        }

        return response()->json([
            'message' => $purchaseOrder
                ? 'Quotation accepted and purchase order created.'
                : 'Quotation accepted successfully.',
            'quotation' => $quotation->load(['buyerRequest', 'supplier', 'items']),
            'purchase_order' => $purchaseOrder,
        ]);
    }

    public function reject(SupplierQuotation $quotation): JsonResponse
    {
        $this->authorize('reject', $quotation);

        $quotation = $this->quotationStatusService->reject($quotation);

        return response()->json([
            'message' => __('Quotation declined.'),
            'quotation' => $quotation->load(self::RELATIONS),
        ]);
    }
}
