<?php

namespace App\Services;

use App\Models\PurchaseOrder;
use App\Models\SupplierQuotation;
use App\Support\DocumentNumber;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseOrderService
{
    public function createFromQuotation(SupplierQuotation $quotation): PurchaseOrder
    {
        return DB::transaction(function () use ($quotation) {
            // Lock the quotation so concurrent requests cannot both create an
            // order (the unique index is the final backstop).
            $quotation = SupplierQuotation::query()->lockForUpdate()->findOrFail($quotation->id);

            if ($quotation->status !== 'accepted') {
                throw ValidationException::withMessages([
                    'status' => 'Only accepted quotations can create purchase orders.',
                ]);
            }

            $quotation->load(['buyerRequest', 'items']);

            if ($quotation->items->isEmpty()) {
                throw ValidationException::withMessages([
                    'items' => 'A quotation must contain at least one item before a purchase order can be created.',
                ]);
            }

            if (PurchaseOrder::query()->where('supplier_quotation_id', $quotation->id)->exists()) {
                throw ValidationException::withMessages([
                    'supplier_quotation_id' => 'A purchase order already exists for this quotation.',
                ]);
            }

            $purchaseOrder = PurchaseOrder::create([
                'buyer_profile_id' => $quotation->buyerRequest->buyer_profile_id,
                'supplier_id' => $quotation->supplier_id,
                'supplier_quotation_id' => $quotation->id,
                'order_number' => DocumentNumber::make('PO'),
                'subtotal' => $quotation->subtotal,
                'delivery_fee' => $quotation->delivery_fee,
                'total_amount' => $quotation->total_amount,
                'currency' => $quotation->currency,
                'status' => 'pending',
                'notes' => $quotation->notes,
            ]);

            foreach ($quotation->items as $quotationItem) {
                $purchaseOrder->items()->create([
                    'product_id' => $quotationItem->product_id,
                    'quantity' => $quotationItem->quantity,
                    'unit' => $quotationItem->unit,
                    'unit_price' => $quotationItem->unit_price,
                    'total_price' => $quotationItem->total_price,
                    'notes' => $quotationItem->notes,
                ]);
            }

            return $purchaseOrder->load('items');
        });
    }
}
