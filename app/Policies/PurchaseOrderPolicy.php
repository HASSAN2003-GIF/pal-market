<?php

namespace App\Policies;

use App\Models\PurchaseOrder;
use App\Models\SupplierQuotation;
use App\Models\User;

/**
 * Suppliers move an order through fulfilment (confirm, process, ship);
 * buyers create it, confirm delivery, complete or cancel it.
 */
class PurchaseOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->buyerProfile !== null || $user->supplier !== null;
    }

    public function view(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return ($user->buyerProfile !== null && $user->buyerProfile->id === $purchaseOrder->buyer_profile_id)
            || ($user->supplier !== null && $user->supplier->id === $purchaseOrder->supplier_id);
    }

    public function create(User $user, SupplierQuotation $quotation): bool
    {
        return $user->activeBuyerProfile()?->id === $quotation->buyerRequest->buyer_profile_id;
    }

    public function confirm(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $this->isActiveSupplier($user, $purchaseOrder);
    }

    public function process(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $this->isActiveSupplier($user, $purchaseOrder);
    }

    public function ship(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $this->isActiveSupplier($user, $purchaseOrder);
    }

    public function deliver(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $this->isActiveBuyer($user, $purchaseOrder);
    }

    public function complete(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $this->isActiveBuyer($user, $purchaseOrder);
    }

    public function cancel(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $this->isActiveBuyer($user, $purchaseOrder);
    }

    private function isActiveSupplier(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->activeSupplier()?->id === $purchaseOrder->supplier_id;
    }

    private function isActiveBuyer(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->activeBuyerProfile()?->id === $purchaseOrder->buyer_profile_id;
    }
}
