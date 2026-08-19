<?php

namespace App\Policies;

use App\Models\PurchaseOrder;
use App\Models\SupplierQuotation;
use App\Models\User;

class PurchaseOrderPolicy
{
    public function view(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->buyerProfile?->id === $purchaseOrder->buyer_profile_id
            || $user->supplier?->id === $purchaseOrder->supplier_id;
    }

    public function create(
        User $user,
        SupplierQuotation $quotation
    ): bool {
        return $user->buyerProfile?->id ===
            $quotation->buyerRequest->buyer_profile_id;
    }

    public function confirm(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->supplier?->id === $purchaseOrder->supplier_id;
    }

    public function process(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->supplier?->id === $purchaseOrder->supplier_id;
    }

    public function ship(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->supplier?->id === $purchaseOrder->supplier_id;
    }

    public function deliver(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->buyerProfile?->id === $purchaseOrder->buyer_profile_id;
    }

    public function complete(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->buyerProfile?->id === $purchaseOrder->buyer_profile_id;
    }

    public function cancel(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->buyerProfile?->id === $purchaseOrder->buyer_profile_id;
    }
}
