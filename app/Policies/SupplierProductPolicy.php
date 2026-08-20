<?php

namespace App\Policies;

use App\Models\SupplierProduct;
use App\Models\User;

class SupplierProductPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->supplier !== null;
    }

    public function view(User $user, SupplierProduct $supplierProduct): bool
    {
        return $user->supplier?->id === $supplierProduct->supplier_id;
    }

    public function create(User $user): bool
    {
        return $user->supplier?->status === 'approved';
    }

    public function update(User $user, SupplierProduct $supplierProduct): bool
    {
        return $user->supplier?->id === $supplierProduct->supplier_id;
    }

    public function delete(User $user, SupplierProduct $supplierProduct): bool
    {
        return $user->supplier?->id === $supplierProduct->supplier_id;
    }
}
