<?php

namespace App\Services;

use App\Models\Supplier;
use App\Models\SupplierInventory;
use App\Models\SupplierLocation;
use App\Models\SupplierProduct;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

class SupplierInventoryService
{
    public function create(
        Supplier $supplier,
        int $supplierProductId,
        int $supplierLocationId,
        int $quantity,
        int $lowStockThreshold = 0,
        bool $isAvailable = true
    ): SupplierInventory {
        $supplierProduct = SupplierProduct::query()
            ->where('id', $supplierProductId)
            ->where('supplier_id', $supplier->id)
            ->first();

        if (! $supplierProduct) {
            throw new AuthorizationException(
                'The selected supplier product does not belong to you.'
            );
        }
        if (! $supplierProduct->is_active) {
            throw ValidationException::withMessages([
                'supplier_product_id' => 'The selected supplier product is inactive.',
            ]);
        }

        $supplierLocation = SupplierLocation::query()
            ->where('id', $supplierLocationId)
            ->where('supplier_id', $supplier->id)
            ->first();

        if (! $supplierLocation) {
            throw new AuthorizationException(
                'The selected supplier location does not belong to you.'
            );
        }

        if ($supplierLocation->status !== 'active') {
            throw ValidationException::withMessages([
                'supplier_location_id' => 'The selected supplier location is inactive.',
            ]);
        }

        $alreadyExists = SupplierInventory::query()
            ->where('supplier_product_id', $supplierProductId)
            ->where('supplier_location_id', $supplierLocationId)
            ->exists();

        if ($alreadyExists) {
            throw ValidationException::withMessages([
                'supplier_location_id' => 'Inventory already exists for this product at this location.',
            ]);
        }

        return SupplierInventory::create([
            'supplier_product_id' => $supplierProductId,
            'supplier_location_id' => $supplierLocationId,
            'quantity' => $quantity,
            'low_stock_threshold' => $lowStockThreshold,
            'is_available' => $isAvailable,
        ]);
    }

    public function update(
        SupplierInventory $supplierInventory,
        ?int $quantity,
        ?int $lowStockThreshold,
        ?bool $isAvailable
    ): SupplierInventory {
        $supplierInventory->update([
            'quantity' => $quantity ?? $supplierInventory->quantity,
            'low_stock_threshold' => $lowStockThreshold ?? $supplierInventory->low_stock_threshold,
            'is_available' => $isAvailable ?? $supplierInventory->is_available,
        ]);

        return $supplierInventory->refresh();
    }
}
