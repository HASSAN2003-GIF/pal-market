<?php

namespace App\Services;

use App\Models\SupplierQuotation;
use Illuminate\Validation\ValidationException;

class SupplierQuotationStatusService
{
    public function submit(
        SupplierQuotation $quotation
    ): SupplierQuotation {
        if ($quotation->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => 'Only draft quotations can be submitted.',
            ]);
        }

        $quotation->update([
            'status' => 'submitted',
        ]);

        return $quotation->refresh();
    }
    public function accept(
    SupplierQuotation $quotation
): SupplierQuotation {
    if ($quotation->status !== 'submitted') {
        throw ValidationException::withMessages([
            'status' => 'Only submitted quotations can be accepted.',
        ]);
    }

    $anotherAcceptedQuotationExists = SupplierQuotation::query()
        ->where('buyer_request_id', $quotation->buyer_request_id)
        ->where('status', 'accepted')
        ->whereKeyNot($quotation->id)
        ->exists();

    if ($anotherAcceptedQuotationExists) {
        throw ValidationException::withMessages([
            'status' => 'Another quotation has already been accepted for this buyer request.',
        ]);
    }

    $quotation->update([
        'status' => 'accepted',
    ]);

    return $quotation->refresh();
}
}