<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use App\Models\SupplierQuotation;
use App\Services\PurchaseOrderService;
use App\Services\PurchaseOrderStatusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PurchaseOrderController extends Controller
{
    public function __construct(
        private PurchaseOrderService $purchaseOrderService,
        private PurchaseOrderStatusService $purchaseOrderStatusService
    ) {}

    public function store(
        Request $request,
        SupplierQuotation $quotation
    ): JsonResponse {
        $this->authorize('create', [PurchaseOrder::class, $quotation]);

        try {
            $purchaseOrder = $this->purchaseOrderService
                ->createFromQuotation($quotation);

            return response()->json([
                'message' => 'Purchase order created successfully.',
                'purchase_order' => $purchaseOrder->load([
                    'buyerProfile',
                    'supplier',
                    'supplierQuotation',
                    'items.product',
                ]),
            ], 201);
        } catch (ValidationException $exception) {
            return response()->json([
                'message' => 'Unable to create purchase order.',
                'errors' => $exception->errors(),
            ], 422);
        }
    }

    public function show(
        PurchaseOrder $purchaseOrder
    ): JsonResponse {
        $this->authorize('view', $purchaseOrder);

        return response()->json([
            'purchase_order' => $purchaseOrder->load([
                'buyerProfile',
                'supplier',
                'supplierQuotation',
                'items.product',
            ]),
        ]);
    }

    public function confirm(
        PurchaseOrder $purchaseOrder
    ): JsonResponse {
        $this->authorize('confirm', $purchaseOrder);

        return $this->transition(
            $purchaseOrder,
            fn () => $this->purchaseOrderStatusService
                ->confirm($purchaseOrder),
            'Purchase order confirmed successfully.'
        );
    }

    public function process(
        PurchaseOrder $purchaseOrder
    ): JsonResponse {
        $this->authorize('process', $purchaseOrder);

        return $this->transition(
            $purchaseOrder,
            fn () => $this->purchaseOrderStatusService
                ->process($purchaseOrder),
            'Purchase order processed successfully.'
        );
    }

    public function ship(
        PurchaseOrder $purchaseOrder
    ): JsonResponse {
        $this->authorize('ship', $purchaseOrder);

        return $this->transition(
            $purchaseOrder,
            fn () => $this->purchaseOrderStatusService
                ->ship($purchaseOrder),
            'Purchase order shipped successfully.'
        );
    }

    public function deliver(
        PurchaseOrder $purchaseOrder
    ): JsonResponse {
        $this->authorize('deliver', $purchaseOrder);

        return $this->transition(
            $purchaseOrder,
            fn () => $this->purchaseOrderStatusService
                ->deliver($purchaseOrder),
            'Purchase order delivered successfully.'
        );
    }

    public function complete(
        PurchaseOrder $purchaseOrder
    ): JsonResponse {
        $this->authorize('complete', $purchaseOrder);

        return $this->transition(
            $purchaseOrder,
            fn () => $this->purchaseOrderStatusService
                ->complete($purchaseOrder),
            'Purchase order completed successfully.'
        );
    }

    public function cancel(
        PurchaseOrder $purchaseOrder
    ): JsonResponse {
        $this->authorize('cancel', $purchaseOrder);

        return $this->transition(
            $purchaseOrder,
            fn () => $this->purchaseOrderStatusService
                ->cancel($purchaseOrder),
            'Purchase order cancelled successfully.'
        );
    }

    private function transition(
        PurchaseOrder $purchaseOrder,
        callable $callback,
        string $message
    ): JsonResponse {
        try {
            $purchaseOrder = $callback();

            return response()->json([
                'message' => $message,
                'purchase_order' => $purchaseOrder->load([
                    'buyerProfile',
                    'supplier',
                    'supplierQuotation',
                    'items.product',
                ]),
            ]);
        } catch (ValidationException $exception) {
            return response()->json([
                'message' => 'Unable to update purchase order.',
                'errors' => $exception->errors(),
            ], 422);
        }
    }
}
