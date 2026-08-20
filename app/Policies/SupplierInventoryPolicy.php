<?php

namespace App\Policies;

use App\Models\SupplierInventory;
use App\Models\User;

class SupplierInventoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->supplier !== null;
    }

    public function view(User $user, SupplierInventory $supplierInventory): bool
    {
        return $user->supplier?->id ===
            $supplierInventory->supplierProduct->supplier_id;
    }

    public function create(User $user): bool
    {
        return $user->supplier?->status === 'approved';
    }

    public function update(User $user, SupplierInventory $supplierInventory): bool
    {
        return $user->supplier?->id ===
            $supplierInventory->supplierProduct->supplier_id;
    }

    public function delete(User $user, SupplierInventory $supplierInventory): bool
    {
        return $user->supplier?->id ===
            $supplierInventory->supplierProduct->supplier_id;
    }
}
