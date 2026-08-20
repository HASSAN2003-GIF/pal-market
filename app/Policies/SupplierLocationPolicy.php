<?php

namespace App\Policies;

use App\Models\SupplierLocation;
use App\Models\User;

class SupplierLocationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->supplier !== null;
    }

    public function view(User $user, SupplierLocation $supplierLocation): bool
    {
        return $user->supplier?->id === $supplierLocation->supplier_id;
    }

    public function create(User $user): bool
    {
        return $user->supplier?->status === 'approved';
    }

    public function update(User $user, SupplierLocation $supplierLocation): bool
    {
        return $user->supplier?->id === $supplierLocation->supplier_id;
    }

    public function delete(User $user, SupplierLocation $supplierLocation): bool
    {
        return $user->supplier?->id === $supplierLocation->supplier_id;
    }
}
