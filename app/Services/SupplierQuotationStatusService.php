<?php

namespace App\Services;

use App\Models\SupplierQuotation;
use Illuminate\Support\Facades\DB;
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

        if ($quotation->buyerRequest->status !== 'open') {
            throw ValidationException::withMessages([
                'status' => 'Quotations can only be submitted for open buyer requests.',
            ]);
        }
        if (
            $quotation->buyerRequest->expires_at !== null &&
            $quotation->buyerRequest->expires_at->isPast()
        ) {
            throw ValidationException::withMessages([
                'status' => 'Quotations cannot be submitted for expired buyer requests.',
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
        return DB::transaction(function () use ($quotation) {

            if ($quotation->status !== 'submitted') {
                throw ValidationException::withMessages([
                    'status' => 'Only submitted quotations can be accepted.',
                ]);
            }
            if (
                $quotation->valid_until !== null &&
                $quotation->valid_until->isPast()
            ) {
                throw ValidationException::withMessages([
                    'status' => 'Expired quotations cannot be accepted.',
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

            SupplierQuotation::query()
                ->where('buyer_request_id', $quotation->buyer_request_id)
                ->where('status', 'submitted')
                ->whereKeyNot($quotation->id)
                ->update([
                    'status' => 'rejected',
                ]);

            $quotation->update([
                'status' => 'accepted',
            ]);

            $quotation->buyerRequest->update([
                'status' => 'closed',
            ]);

            return $quotation->refresh();
        });
    }
}
