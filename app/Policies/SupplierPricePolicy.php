<?php

namespace App\Policies;

use App\Models\SupplierPrice;
use App\Models\User;

class SupplierPricePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->supplier !== null;
    }

    public function view(User $user, SupplierPrice $supplierPrice): bool
    {
        return $user->supplier?->id ===
            $supplierPrice->supplierProduct->supplier_id;
    }

    public function create(User $user): bool
    {
        return $user->activeSupplier() !== null;
    }

    public function update(User $user, SupplierPrice $supplierPrice): bool
    {
        return $user->activeSupplier()?->id ===
            $supplierPrice->supplierProduct->supplier_id;
    }

    public function delete(User $user, SupplierPrice $supplierPrice): bool
    {
        return $user->activeSupplier()?->id ===
            $supplierPrice->supplierProduct->supplier_id;
    }
}
