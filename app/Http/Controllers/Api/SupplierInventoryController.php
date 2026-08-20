<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SupplierInventory;
use App\Services\SupplierInventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SupplierInventoryController extends Controller
{
    public function __construct(
        private SupplierInventoryService $supplierInventoryService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', SupplierInventory::class);

        $supplier = $request->user()->supplier;

        $inventories = SupplierInventory::query()
            ->whereHas('supplierProduct', function ($query) use ($supplier) {
                $query->where('supplier_id', $supplier->id);
            })
            ->with([
                'supplierProduct.product.category',
                'supplierProduct.product.brand',
                'supplierLocation',
            ])
            ->latest()
            ->get();

        return response()->json([
            'inventories' => $inventories,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', SupplierInventory::class);

        $validated = $request->validate([
            'supplier_product_id' => ['required', 'integer', 'exists:supplier_products,id'],
            'supplier_location_id' => ['required', 'integer', 'exists:supplier_locations,id'],
            'quantity' => ['required', 'integer', 'min:0'],
            'low_stock_threshold' => ['sometimes', 'integer', 'min:0'],
            'is_available' => ['sometimes', 'boolean'],
        ]);

        try {
            $inventory = $this->supplierInventoryService->create(
                $request->user()->supplier,
                $validated['supplier_product_id'],
                $validated['supplier_location_id'],
                $validated['quantity'],
                $validated['low_stock_threshold'] ?? 0,
                $validated['is_available'] ?? true
            );

            return response()->json([
                'message' => 'Supplier inventory created successfully.',
                'inventory' => $inventory->load([
                    'supplierProduct.product.category',
                    'supplierProduct.product.brand',
                    'supplierLocation',
                ]),
            ], 201);
        } catch (ValidationException $exception) {
            return response()->json([
                'message' => 'Unable to create supplier inventory.',
                'errors' => $exception->errors(),
            ], 422);
        }
    }

    public function show(
        SupplierInventory $supplierInventory
    ): JsonResponse {
        $this->authorize('view', $supplierInventory);

        return response()->json([
            'inventory' => $supplierInventory->load([
                'supplierProduct.product.category',
                'supplierProduct.product.brand',
                'supplierLocation',
            ]),
        ]);
    }

    public function update(
        Request $request,
        SupplierInventory $supplierInventory
    ): JsonResponse {
        $this->authorize('update', $supplierInventory);

        $validated = $request->validate([
            'quantity' => ['sometimes', 'integer', 'min:0'],
            'low_stock_threshold' => ['sometimes', 'integer', 'min:0'],
            'is_available' => ['sometimes', 'boolean'],
        ]);

        $inventory = $this->supplierInventoryService->update(
            $supplierInventory,
            $validated['quantity'] ?? null,
            $validated['low_stock_threshold'] ?? null,
            $validated['is_available'] ?? null
        );

        return response()->json([
            'message' => 'Supplier inventory updated successfully.',
            'inventory' => $inventory->load([
                'supplierProduct.product.category',
                'supplierProduct.product.brand',
                'supplierLocation',
            ]),
        ]);
    }

    public function destroy(
        SupplierInventory $supplierInventory
    ): JsonResponse {
        $this->authorize('delete', $supplierInventory);

        $supplierInventory->delete();

        return response()->json([
            'message' => 'Supplier inventory deleted successfully.',
        ]);
    }
}
