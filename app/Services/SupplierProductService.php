<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use Illuminate\Validation\ValidationException;

class SupplierProductService
{
    public function create(
        Supplier $supplier,
        int $productId,
        ?string $supplierSku = null,
        ?string $description = null
    ): SupplierProduct {
        if ($supplier->status !== 'approved') {
            throw ValidationException::withMessages([
                'supplier_id' => 'Only approved suppliers can register products.',
            ]);
        }

        $product = Product::query()
            ->where('id', $productId)
            ->where('is_active', true)
            ->first();

        if (! $product) {
            throw ValidationException::withMessages([
                'product_id' => 'The selected product is not available.',
            ]);
        }

        $alreadyExists = $supplier->supplierProducts()
            ->where('product_id', $productId)
            ->exists();

        if ($alreadyExists) {
            throw ValidationException::withMessages([
                'product_id' => 'This product is already registered by this supplier.',
            ]);
        }

        return $supplier->supplierProducts()->create([
            'product_id' => $productId,
            'supplier_sku' => $supplierSku,
            'description' => $description,
            'is_active' => true,
        ]);
    }

    public function update(
        SupplierProduct $supplierProduct,
        ?string $supplierSku,
        ?string $description,
        ?bool $isActive
    ): SupplierProduct {
        $supplierProduct->update([
            'supplier_sku' => $supplierSku,
            'description' => $description,
            'is_active' => $isActive ?? $supplierProduct->is_active,
        ]);

        return $supplierProduct->refresh();
    }
}
