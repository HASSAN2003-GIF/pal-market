<?php

namespace App\Services;

use App\Models\BuyerRequest;
use App\Models\Supplier;
use App\Models\SupplierQuotation;
use App\Models\SupplierQuotationItem;
use App\Support\DocumentNumber;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SupplierQuotationService
{
    public function __construct(
        private SupplierQuotationStatusService $statusService,
    ) {}

    public function create(
        BuyerRequest $buyerRequest,
        Supplier $supplier,
        array $terms = [],
    ): SupplierQuotation {
        if ($buyerRequest->status !== 'open') {
            throw ValidationException::withMessages([
                'buyer_request_id' => 'Quotations can only be created for open buyer requests.',
            ]);
        }

        if ($buyerRequest->expires_at !== null && $buyerRequest->expires_at->isPast()) {
            throw ValidationException::withMessages([
                'buyer_request_id' => 'Quotations cannot be created for expired buyer requests.',
            ]);
        }

        if ($supplier->status !== 'approved') {
            throw ValidationException::withMessages([
                'supplier_id' => 'Only approved suppliers can create quotations.',
            ]);
        }

        if ($supplier->quotations()->where('buyer_request_id', $buyerRequest->id)->exists()) {
            throw ValidationException::withMessages([
                'buyer_request_id' => 'This supplier has already created a quotation for this buyer request.',
            ]);
        }

        $deliveryFee = (float) ($terms['delivery_fee'] ?? 0);

        return SupplierQuotation::create([
            'buyer_request_id' => $buyerRequest->id,
            'supplier_id' => $supplier->id,
            'quotation_number' => DocumentNumber::make('QUO'),
            'subtotal' => 0,
            'delivery_fee' => $deliveryFee,
            'total_amount' => $deliveryFee,
            'currency' => 'TZS',
            'status' => 'draft',
            'valid_until' => $terms['valid_until'] ?? null,
            'notes' => $terms['notes'] ?? null,
        ]);
    }

    /**
     * Creates a complete quotation in one transaction and optionally submits
     * it. Either everything is saved or nothing is.
     *
     * @param  array{items: array<int, array{product_id: int, unit_price: float|int|string, quantity?: ?int, unit?: ?string, notes?: ?string}>, delivery_fee?: float|int|string|null, valid_until?: ?string, notes?: ?string, submit?: bool}  $payload
     */
    public function createWithItems(BuyerRequest $buyerRequest, Supplier $supplier, array $payload): SupplierQuotation
    {
        return DB::transaction(function () use ($buyerRequest, $supplier, $payload) {
            $quotation = $this->create($buyerRequest, $supplier, $payload);
            $requestedItems = $buyerRequest->items()->get()->keyBy('product_id');

            foreach ($payload['items'] as $index => $line) {
                $requested = $requestedItems->get((int) $line['product_id']);

                try {
                    $this->addItem(
                        $quotation,
                        (int) $line['product_id'],
                        (int) ($line['quantity'] ?? $requested?->quantity ?? 0),
                        $line['unit'] ?? $requested?->unit ?? 'piece',
                        (float) $line['unit_price'],
                        $line['notes'] ?? null,
                        recalculate: false,
                    );
                } catch (ValidationException $exception) {
                    // Point the error at the offending line for the form.
                    throw ValidationException::withMessages(collect($exception->errors())
                        ->mapWithKeys(fn ($messages, $field) => ["items.{$index}.{$field}" => $messages])
                        ->all());
                }
            }

            $quotation = $this->recalculateTotals($quotation);

            return ($payload['submit'] ?? false)
                ? $this->statusService->submit($quotation)
                : $quotation;
        });
    }

    public function updateTerms(SupplierQuotation $quotation, array $terms): SupplierQuotation
    {
        $this->ensureDraft($quotation);

        $quotation->update(array_intersect_key($terms, array_flip(['delivery_fee', 'valid_until', 'notes'])));

        return $this->recalculateTotals($quotation);
    }

    public function addItem(
        SupplierQuotation $quotation,
        int $productId,
        int $quantity,
        string $unit,
        float $unitPrice,
        ?string $notes = null,
        bool $recalculate = true,
    ): SupplierQuotationItem {
        $this->ensureDraft($quotation);

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

        if (! $quotation->buyerRequest->items()->where('product_id', $productId)->exists()) {
            throw ValidationException::withMessages([
                'product_id' => 'This product was not requested by the buyer.',
            ]);
        }

        $supplierOffersProduct = $quotation->supplier
            ->products()
            ->where('products.id', $productId)
            ->wherePivot('is_active', true)
            ->exists();

        if (! $supplierOffersProduct) {
            throw ValidationException::withMessages([
                'product_id' => 'This product is not in your active supplier catalog. Add it under My catalog first.',
            ]);
        }

        if ($quotation->items()->where('product_id', $productId)->exists()) {
            throw ValidationException::withMessages([
                'product_id' => 'This product is already priced on the quotation.',
            ]);
        }

        $item = SupplierQuotationItem::create([
            'supplier_quotation_id' => $quotation->id,
            'product_id' => $productId,
            'quantity' => $quantity,
            'unit' => $unit,
            'unit_price' => $unitPrice,
            'total_price' => round($quantity * $unitPrice, 2),
            'notes' => $notes,
        ]);

        if ($recalculate) {
            $this->recalculateTotals($quotation);
        }

        return $item;
    }

    public function removeItem(SupplierQuotationItem $item): SupplierQuotation
    {
        $quotation = $item->supplierQuotation;
        $this->ensureDraft($quotation);

        $item->delete();

        return $this->recalculateTotals($quotation);
    }

    public function recalculateTotals(SupplierQuotation $quotation): SupplierQuotation
    {
        $subtotal = (float) $quotation->items()->sum('total_price');

        $quotation->update([
            'subtotal' => $subtotal,
            'total_amount' => $subtotal + (float) $quotation->delivery_fee,
        ]);

        return $quotation->refresh();
    }

    private function ensureDraft(SupplierQuotation $quotation): void
    {
        if ($quotation->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => 'Only draft quotations can be modified.',
            ]);
        }
    }
}
