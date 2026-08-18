<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BuyerRequest;
use App\Models\SupplierQuotation;
use App\Services\SupplierQuotationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SupplierQuotationController extends Controller
{
    public function __construct(
        private SupplierQuotationService $quotationService
    ) {}

    public function store(BuyerRequest $buyerRequest): JsonResponse
    {
        $user = request()->user();

        $supplier = $user->supplier;

        if (! $supplier) {
            return response()->json([
                'message' => 'You do not have a supplier profile.',
            ], 403);
        }

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
        $user = $request->user();

        $supplier = $user->supplier;

        if (! $supplier) {
            return response()->json([
                'message' => 'You do not have a supplier profile.',
            ], 403);
        }

        if ($quotation->supplier_id !== $supplier->id) {
            return response()->json([
                'message' => 'You are not authorized to modify this quotation.',
            ], 403);
        }

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
}