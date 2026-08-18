<?php

namespace App\Policies;

use App\Models\SupplierQuotation;
use App\Models\User;

class SupplierQuotationPolicy
{
    public function view(User $user, SupplierQuotation $quotation): bool
    {
        return $user->supplier?->id === $quotation->supplier_id
            || $user->buyerProfile?->id === $quotation->buyerRequest->buyer_profile_id;
    }

    public function create(User $user): bool
    {
        return $user->supplier?->status === 'approved';
    }

    public function update(User $user, SupplierQuotation $quotation): bool
    {
        return $user->supplier?->id === $quotation->supplier_id;
    }

    public function submit(User $user, SupplierQuotation $quotation): bool
    {
        return $user->supplier?->id === $quotation->supplier_id;
    }

    public function accept(User $user, SupplierQuotation $quotation): bool
    {
        return $user->buyerProfile?->id === $quotation->buyerRequest->buyer_profile_id;
    }
}
