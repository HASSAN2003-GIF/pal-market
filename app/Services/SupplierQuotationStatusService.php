<?php

namespace App\Services;

use App\Models\BuyerRequest;
use App\Models\SupplierQuotation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SupplierQuotationStatusService
{
    public function submit(SupplierQuotation $quotation): SupplierQuotation
    {
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

        if ($quotation->buyerRequest->expires_at !== null && $quotation->buyerRequest->expires_at->isPast()) {
            throw ValidationException::withMessages([
                'status' => 'Quotations cannot be submitted for expired buyer requests.',
            ]);
        }

        if (! $quotation->items()->exists()) {
            throw ValidationException::withMessages([
                'items' => 'Price at least one requested item before submitting the quotation.',
            ]);
        }

        if ($quotation->valid_until !== null && $quotation->valid_until->isPast()) {
            throw ValidationException::withMessages([
                'valid_until' => 'The quotation validity date must be in the future.',
            ]);
        }

        $quotation->update([
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        return $quotation->refresh();
    }

    /**
     * A supplier may withdraw a submitted bid while the buyer has not decided.
     */
    public function withdraw(SupplierQuotation $quotation): SupplierQuotation
    {
        if (! in_array($quotation->status, ['draft', 'submitted'], true)) {
            throw ValidationException::withMessages([
                'status' => 'Only draft or submitted quotations can be withdrawn.',
            ]);
        }

        $quotation->update(['status' => 'cancelled']);

        return $quotation->refresh();
    }

    public function reject(SupplierQuotation $quotation): SupplierQuotation
    {
        if ($quotation->status !== 'submitted') {
            throw ValidationException::withMessages([
                'status' => 'Only submitted quotations can be declined.',
            ]);
        }

        $quotation->update(['status' => 'rejected']);

        return $quotation->refresh();
    }

    /**
     * Accepting one quotation closes the request and declines the others.
     *
     * The buyer request row is locked for the duration so two concurrent
     * accepts cannot both succeed.
     */
    public function accept(SupplierQuotation $quotation): SupplierQuotation
    {
        return DB::transaction(function () use ($quotation) {
            $buyerRequest = BuyerRequest::query()->lockForUpdate()->findOrFail($quotation->buyer_request_id);
            $quotation = SupplierQuotation::query()->lockForUpdate()->findOrFail($quotation->id);

            if ($quotation->status !== 'submitted') {
                throw ValidationException::withMessages([
                    'status' => 'Only submitted quotations can be accepted.',
                ]);
            }

            if ($quotation->valid_until !== null && $quotation->valid_until->isPast()) {
                throw ValidationException::withMessages([
                    'status' => 'Expired quotations cannot be accepted.',
                ]);
            }

            if (SupplierQuotation::query()
                ->where('buyer_request_id', $buyerRequest->id)
                ->where('status', 'accepted')
                ->whereKeyNot($quotation->id)
                ->exists()) {
                throw ValidationException::withMessages([
                    'status' => 'Another quotation has already been accepted for this buyer request.',
                ]);
            }

            if ($buyerRequest->status !== 'open') {
                throw ValidationException::withMessages([
                    'status' => "Quotations cannot be accepted on a {$buyerRequest->status} request.",
                ]);
            }

            SupplierQuotation::query()
                ->where('buyer_request_id', $buyerRequest->id)
                ->where('status', 'submitted')
                ->whereKeyNot($quotation->id)
                ->update(['status' => 'rejected']);

            $quotation->update(['status' => 'accepted']);
            $buyerRequest->update(['status' => 'closed']);

            return $quotation->refresh();
        });
    }

    public function expire(SupplierQuotation $quotation): SupplierQuotation
    {
        if ($quotation->status !== 'submitted') {
            throw ValidationException::withMessages([
                'status' => 'Only submitted quotations can expire.',
            ]);
        }

        if ($quotation->valid_until === null || $quotation->valid_until->isFuture()) {
            throw ValidationException::withMessages([
                'status' => 'Only quotations whose expiration time has passed can expire.',
            ]);
        }

        $quotation->update(['status' => 'expired']);

        return $quotation->refresh();
    }
}
