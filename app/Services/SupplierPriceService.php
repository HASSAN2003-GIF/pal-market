<?php

namespace App\Services;

use App\Models\Supplier;
use App\Models\SupplierPrice;
use App\Models\SupplierProduct;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class SupplierPriceService
{
    public function create(
        Supplier $supplier,
        int $supplierProductId,
        float $price,
        string $currency = 'TZS',
        string $unit = 'piece',
        bool $isActive = true,
        ?string $effectiveFrom = null,
        ?string $effectiveUntil = null
    ): SupplierPrice {
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

        $from = $effectiveFrom
            ? Carbon::parse($effectiveFrom)
            : null;

        $until = $effectiveUntil
            ? Carbon::parse($effectiveUntil)
            : null;

        $this->validatePeriod($from, $until);

        if ($isActive && $this->hasOverlappingActivePrice(
            $supplierProductId,
            $from,
            $until
        )) {
            throw ValidationException::withMessages([
                'effective_from' => 'The active price period overlaps an existing active price.',
            ]);
        }

        return SupplierPrice::create([
            'supplier_product_id' => $supplierProductId,
            'price' => $price,
            'currency' => $currency,
            'unit' => $unit,
            'is_active' => $isActive,
            'effective_from' => $from,
            'effective_until' => $until,
        ]);
    }

    public function update(
        SupplierPrice $supplierPrice,
        ?float $price,
        ?string $currency,
        ?string $unit,
        ?bool $isActive,
        ?string $effectiveFrom,
        ?string $effectiveUntil
    ): SupplierPrice {
        $newPrice = $price ?? (float) $supplierPrice->price;
        $newCurrency = $currency ?? $supplierPrice->currency;
        $newUnit = $unit ?? $supplierPrice->unit;
        $newIsActive = $isActive ?? $supplierPrice->is_active;

        $newFrom = $effectiveFrom !== null
            ? Carbon::parse($effectiveFrom)
            : $supplierPrice->effective_from;

        $newUntil = $effectiveUntil !== null
            ? Carbon::parse($effectiveUntil)
            : $supplierPrice->effective_until;

        $this->validatePeriod($newFrom, $newUntil);

        if ($newIsActive && $this->hasOverlappingActivePrice(
            $supplierPrice->supplier_product_id,
            $newFrom,
            $newUntil,
            $supplierPrice->id
        )) {
            throw ValidationException::withMessages([
                'effective_from' => 'The active price period overlaps an existing active price.',
            ]);
        }

        $supplierPrice->update([
            'price' => $newPrice,
            'currency' => $newCurrency,
            'unit' => $newUnit,
            'is_active' => $newIsActive,
            'effective_from' => $newFrom,
            'effective_until' => $newUntil,
        ]);

        return $supplierPrice->refresh();
    }

    private function validatePeriod(
        ?Carbon $effectiveFrom,
        ?Carbon $effectiveUntil
    ): void {
        if (
            $effectiveFrom !== null &&
            $effectiveUntil !== null &&
            $effectiveUntil->lte($effectiveFrom)
        ) {
            throw ValidationException::withMessages([
                'effective_until' => 'The effective until date must be after the effective from date.',
            ]);
        }
    }

    private function hasOverlappingActivePrice(
        int $supplierProductId,
        ?Carbon $effectiveFrom,
        ?Carbon $effectiveUntil,
        ?int $exceptId = null
    ): bool {
        $query = SupplierPrice::query()
            ->where('supplier_product_id', $supplierProductId)
            ->where('is_active', true);

        if ($exceptId !== null) {
            $query->where('id', '!=', $exceptId);
        }

        $query->where(function ($query) use ($effectiveFrom) {
            $query
                ->where(function ($query) {
                    $query
                        ->whereNull('effective_from')
                        ->orWhere(function ($query) {
                            $query->where(
                                'effective_from',
                                '<=',
                                $effectiveUntil ?? '9999-12-31 23:59:59'
                            );
                        });
                })
                ->where(function ($query) use ($effectiveFrom) {
                    $query
                        ->whereNull('effective_until')
                        ->orWhere(function ($query) use ($effectiveFrom) {
                            $query->where(
                                'effective_until',
                                '>=',
                                $effectiveFrom ?? '0001-01-01 00:00:00'
                            );
                        });
                });
        });

        return $query->exists();
    }
}
