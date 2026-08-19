<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BuyerRequest;
use App\Models\SupplierQuotation;
use App\Services\SupplierQuotationService;
use App\Services\SupplierQuotationStatusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SupplierQuotationController extends Controller
{
    public function __construct(
        private SupplierQuotationService $quotationService,
        private SupplierQuotationStatusService $quotationStatusService
    ) {}

    public function store(BuyerRequest $buyerRequest): JsonResponse
    {
        $supplier = request()->user()->supplier;

        if (! $supplier) {
            return response()->json([
                'message' => 'You do not have a supplier profile.',
            ], 403);
        }

        if ($supplier->status !== 'approved') {
            return response()->json([
                'message' => 'Unable to create quotation.',
                'errors' => [
                    'supplier_id' => [
                        'Your supplier account must be approved before creating a quotation.',
                    ],
                ],
            ], 422);
        }

        $this->authorize('create', SupplierQuotation::class);

        try {
            $quotation = $this->quotationService->create(
                $buyerRequest,
                $supplier
            );

            return response()->json([
                'message' => 'Quotation created successfully.',
                'quotation' => $quotation->load([
                    'buyerRequest',
                    'supplier',
                    'items',
                ]),
            ], 201);
        } catch (ValidationException $exception) {
            return response()->json([
                'message' => 'Unable to create quotation.',
                'errors' => $exception->errors(),
            ], 422);
        }
    }

    public function addItem(
        Request $request,
        SupplierQuotation $quotation
    ): JsonResponse {
        $this->authorize('update', $quotation);

        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'unit' => ['required', 'string', 'max:50'],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $item = $this->quotationService->addItem(
                $quotation,
                $validated['product_id'],
                $validated['quantity'],
                $validated['unit'],
                (float) $validated['unit_price'],
                $validated['notes'] ?? null
            );

            return response()->json([
                'message' => 'Quotation item added successfully.',
                'item' => $item->load('product'),
                'quotation' => $quotation->fresh()->load('items'),
            ], 201);
        } catch (ValidationException $exception) {
            return response()->json([
                'message' => 'Unable to add quotation item.',
                'errors' => $exception->errors(),
            ], 422);
        }
    }

    public function submit(
        Request $request,
        SupplierQuotation $quotation
    ): JsonResponse {
        $this->authorize('submit', $quotation);

        try {
            $quotation = $this->quotationStatusService->submit($quotation);

            return response()->json([
                'message' => 'Quotation submitted successfully.',
                'quotation' => $quotation->load([
                    'buyerRequest',
                    'supplier',
                    'items',
                ]),
            ]);
        } catch (ValidationException $exception) {
            return response()->json([
                'message' => 'Unable to submit quotation.',
                'errors' => $exception->errors(),
            ], 422);
        }
    }

    public function accept(
        Request $request,
        SupplierQuotation $quotation
    ): JsonResponse {
        $this->authorize('accept', $quotation);

        try {
            $quotation = $this->quotationStatusService->accept($quotation);

            return response()->json([
                'message' => 'Quotation accepted successfully.',
                'quotation' => $quotation->load([
                    'buyerRequest',
                    'supplier',
                    'items',
                ]),
            ]);
        } catch (ValidationException $exception) {
            return response()->json([
                'message' => 'Unable to accept quotation.',
                'errors' => $exception->errors(),
            ], 422);
        }
    }

    public function show(
        Request $request,
        SupplierQuotation $quotation
    ): JsonResponse {
        $this->authorize('view', $quotation);

        return response()->json([
            'quotation' => $quotation->load([
                'buyerRequest',
                'supplier',
                'items.product',
            ]),
        ]);
    }
}
