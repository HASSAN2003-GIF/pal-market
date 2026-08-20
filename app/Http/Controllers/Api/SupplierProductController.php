<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SupplierProduct;
use App\Services\SupplierProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SupplierProductController extends Controller
{
    public function __construct(
        private SupplierProductService $supplierProductService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', SupplierProduct::class);

        $supplier = $request->user()->supplier;

        $supplierProducts = $supplier
            ->supplierProducts()
            ->with([
                'product.category',
                'product.brand',
                'inventories.supplierLocation',
                'prices',
            ])
            ->latest()
            ->get();

        return response()->json([
            'supplier_products' => $supplierProducts,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', SupplierProduct::class);

        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'supplier_sku' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $supplierProduct = $this->supplierProductService->create(
                $request->user()->supplier,
                $validated['product_id'],
                $validated['supplier_sku'] ?? null,
                $validated['description'] ?? null
            );

            return response()->json([
                'message' => 'Product added to supplier catalog successfully.',
                'supplier_product' => $supplierProduct->load([
                    'product.category',
                    'product.brand',
                ]),
            ], 201);
        } catch (ValidationException $exception) {
            return response()->json([
                'message' => 'Unable to add product.',
                'errors' => $exception->errors(),
            ], 422);
        }
    }

    public function show(
        SupplierProduct $supplierProduct
    ): JsonResponse {
        $this->authorize('view', $supplierProduct);

        return response()->json([
            'supplier_product' => $supplierProduct->load([
                'product.category',
                'product.brand',
                'inventories.supplierLocation',
                'prices',
            ]),
        ]);
    }

    public function update(
        Request $request,
        SupplierProduct $supplierProduct
    ): JsonResponse {
        $this->authorize('update', $supplierProduct);

        $validated = $request->validate([
            'supplier_sku' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $supplierProduct = $this->supplierProductService->update(
            $supplierProduct,
            $validated['supplier_sku'] ?? null,
            $validated['description'] ?? null,
            $validated['is_active'] ?? null
        );

        return response()->json([
            'message' => 'Supplier product updated successfully.',
            'supplier_product' => $supplierProduct->load([
                'product.category',
                'product.brand',
            ]),
        ]);
    }

    public function destroy(
        SupplierProduct $supplierProduct
    ): JsonResponse {
        $this->authorize('delete', $supplierProduct);

        $supplierProduct->delete();

        return response()->json([
            'message' => 'Supplier product removed successfully.',
        ]);
    }
}
