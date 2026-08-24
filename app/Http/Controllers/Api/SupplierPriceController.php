<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SupplierPrice;
use App\Services\SupplierPriceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SupplierPriceController extends Controller
{
    public function __construct(
        private SupplierPriceService $supplierPriceService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', SupplierPrice::class);

        $supplier = $request->user()->supplier;

        $prices = SupplierPrice::query()
            ->whereHas('supplierProduct', function ($query) use ($supplier) {
                $query->where('supplier_id', $supplier->id);
            })
            ->with([
                'supplierProduct.product.category',
                'supplierProduct.product.brand',
            ])
            ->latest()
            ->get();

        return response()->json([
            'supplier_prices' => $prices,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', SupplierPrice::class);

        $validated = $request->validate([
            'supplier_product_id' => [
                'required',
                'integer',
                'exists:supplier_products,id',
            ],
            'price' => [
                'required',
                'numeric',
                'gt:0',
            ],
            'currency' => [
                'sometimes',
                'string',
                'size:3',
            ],
            'unit' => [
                'sometimes',
                'string',
                'max:255',
            ],
            'is_active' => [
                'sometimes',
                'boolean',
            ],
            'effective_from' => [
                'nullable',
                'date',
            ],
            'effective_until' => [
                'nullable',
                'date',
                'after:effective_from',
            ],
        ]);

        try {
            $supplierPrice = $this->supplierPriceService->create(
                $request->user()->supplier,
                $validated['supplier_product_id'],
                (float) $validated['price'],
                $validated['currency'] ?? 'TZS',
                $validated['unit'] ?? 'piece',
                $validated['is_active'] ?? true,
                $validated['effective_from'] ?? null,
                $validated['effective_until'] ?? null
            );

            return response()->json([
                'message' => 'Supplier price created successfully.',
                'supplier_price' => $supplierPrice->load([
                    'supplierProduct.product.category',
                    'supplierProduct.product.brand',
                ]),
            ], 201);
        } catch (ValidationException $exception) {
            return response()->json([
                'message' => 'Unable to create supplier price.',
                'errors' => $exception->errors(),
            ], 422);
        }
    }

    public function show(SupplierPrice $supplierPrice): JsonResponse
    {
        $this->authorize('view', $supplierPrice);

        return response()->json([
            'supplier_price' => $supplierPrice->load([
                'supplierProduct.product.category',
                'supplierProduct.product.brand',
            ]),
        ]);
    }

    public function update(
        Request $request,
        SupplierPrice $supplierPrice
    ): JsonResponse {
        $this->authorize('update', $supplierPrice);

        $validated = $request->validate([
            'price' => [
                'sometimes',
                'numeric',
                'gt:0',
            ],
            'currency' => [
                'sometimes',
                'string',
                'size:3',
            ],
            'unit' => [
                'sometimes',
                'string',
                'max:255',
            ],
            'is_active' => [
                'sometimes',
                'boolean',
            ],
            'effective_from' => [
                'nullable',
                'date',
            ],
            'effective_until' => [
                'nullable',
                'date',
                'after:effective_from',
            ],
        ]);

        try {
            $supplierPrice = $this->supplierPriceService->update(
                $supplierPrice,
                isset($validated['price'])
                    ? (float) $validated['price']
                    : null,
                $validated['currency'] ?? null,
                $validated['unit'] ?? null,
                $validated['is_active'] ?? null,
                array_key_exists('effective_from', $validated)
                    ? $validated['effective_from']
                    : null,
                array_key_exists('effective_until', $validated)
                    ? $validated['effective_until']
                    : null
            );

            return response()->json([
                'message' => 'Supplier price updated successfully.',
                'supplier_price' => $supplierPrice->load([
                    'supplierProduct.product.category',
                    'supplierProduct.product.brand',
                ]),
            ]);
        } catch (ValidationException $exception) {
            return response()->json([
                'message' => 'Unable to update supplier price.',
                'errors' => $exception->errors(),
            ], 422);
        }
    }

    public function destroy(SupplierPrice $supplierPrice): JsonResponse
    {
        $this->authorize('delete', $supplierPrice);

        $supplierPrice->delete();

        return response()->json([
            'message' => 'Supplier price deleted successfully.',
        ]);
    }
}
