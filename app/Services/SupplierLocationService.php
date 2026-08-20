<?php

namespace App\Services;

use App\Models\Supplier;
use App\Models\SupplierLocation;
use Illuminate\Validation\ValidationException;

class SupplierLocationService
{
    public function create(
        Supplier $supplier,
        string $name,
        string $address,
        string $region,
        ?string $district = null,
        ?string $ward = null,
        ?string $latitude = null,
        ?string $longitude = null,
        ?string $phone = null,
        bool $isPrimary = false
    ): SupplierLocation {
        if ($supplier->status !== 'approved') {
            throw ValidationException::withMessages([
                'supplier_id' => 'Only approved suppliers can create locations.',
            ]);
        }

        if ($isPrimary) {
            $supplier->locations()
                ->where('is_primary', true)
                ->update(['is_primary' => false]);
        }

        return $supplier->locations()->create([
            'name' => $name,
            'address' => $address,
            'region' => $region,
            'district' => $district,
            'ward' => $ward,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'phone' => $phone,
            'is_primary' => $isPrimary,
            'status' => 'active',
        ]);
    }

    public function update(
        SupplierLocation $supplierLocation,
        string $name,
        string $address,
        string $region,
        ?string $district,
        ?string $ward,
        ?string $latitude,
        ?string $longitude,
        ?string $phone,
        ?bool $isPrimary,
        ?string $status
    ): SupplierLocation {
        if ($isPrimary === true) {
            $supplierLocation->supplier
                ->locations()
                ->where('id', '!=', $supplierLocation->id)
                ->where('is_primary', true)
                ->update(['is_primary' => false]);
        }

        $supplierLocation->update([
            'name' => $name,
            'address' => $address,
            'region' => $region,
            'district' => $district,
            'ward' => $ward,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'phone' => $phone,
            'is_primary' => $isPrimary ?? $supplierLocation->is_primary,
            'status' => $status ?? $supplierLocation->status,
        ]);

        return $supplierLocation->refresh();
    }
}
