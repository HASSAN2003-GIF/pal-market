<?php

namespace App\Services;

use App\Models\SupplierQuotation;
use App\Models\SupplierQuotationItem;
use Illuminate\Validation\ValidationException;

class SupplierQuotationService
{
    public function addItem(
        SupplierQuotation $quotation,
        int $productId,
        int $quantity,
        string $unit,
        float $unitPrice,
        ?string $notes = null
    ): SupplierQuotationItem {
        if ($quotation->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => 'Only draft quotations can be modified.',
            ]);
        }
        if ($quantity <= 0) {
            throw ValidationException::withMessages([
                'quantity' => 'Quantity must be greater than zero.',
            ]);
        }

        if ($unitPrice < 0) {
            throw ValidationException::withMessages([
                'unit_price' => 'Unit price cannot be negative.',
            ]);
        }
        $productIsRequested = $quotation->buyerRequest
            ->items()
            ->where('product_id', $productId)
            ->exists();

        if (! $productIsRequested) {
            throw ValidationException::withMessages([
                'product_id' => 'This product was not requested by the buyer.',
            ]);
        }

        $supplierOffersProduct = $quotation->supplier
            ->products()
            ->where('products.id', $productId)
            ->exists();

        if (! $supplierOffersProduct) {
            throw ValidationException::withMessages([
                'product_id' => 'This supplier does not offer this product.',
            ]);
        }

        $item = SupplierQuotationItem::create([
            'supplier_quotation_id' => $quotation->id,
            'product_id' => $productId,
            'quantity' => $quantity,
            'unit' => $unit,
            'unit_price' => $unitPrice,
            'total_price' => $quantity * $unitPrice,
            'notes' => $notes,
        ]);

        $this->recalculateTotals($quotation);

        return $item;
    }

    public function recalculateTotals(
        SupplierQuotation $quotation
    ): SupplierQuotation {
        $subtotal = $quotation->items()->sum('total_price');

        $quotation->update([
            'subtotal' => $subtotal,
            'total_amount' => $subtotal + $quotation->delivery_fee,
        ]);

        return $quotation->refresh();
    }
}
