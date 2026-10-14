<?php

namespace App\Policies;

use App\Models\SupplierQuotation;
use App\Models\User;

class SupplierQuotationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->supplier !== null;
    }

    /**
     * Suppliers see their own quotations. Buyers see quotations on their
     * requests, but never a supplier's unsubmitted draft.
     */
    public function view(User $user, SupplierQuotation $quotation): bool
    {
        if ($user->supplier !== null && $user->supplier->id === $quotation->supplier_id) {
            return true;
        }

        return $quotation->status !== 'draft'
            && $user->buyerProfile !== null
            && $user->buyerProfile->id === $quotation->buyerRequest->buyer_profile_id;
    }

    public function create(User $user): bool
    {
        return $user->activeSupplier() !== null;
    }

    public function update(User $user, SupplierQuotation $quotation): bool
    {
        return $user->activeSupplier()?->id === $quotation->supplier_id
            && $quotation->status === 'draft';
    }

    public function submit(User $user, SupplierQuotation $quotation): bool
    {
        return $user->activeSupplier()?->id === $quotation->supplier_id;
    }

    public function withdraw(User $user, SupplierQuotation $quotation): bool
    {
        return $user->activeSupplier()?->id === $quotation->supplier_id;
    }

    public function accept(User $user, SupplierQuotation $quotation): bool
    {
        return $user->activeBuyerProfile()?->id === $quotation->buyerRequest->buyer_profile_id;
    }

    public function reject(User $user, SupplierQuotation $quotation): bool
    {
        return $this->accept($user, $quotation);
    }
}
