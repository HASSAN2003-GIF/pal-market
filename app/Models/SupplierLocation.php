<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplierLocation extends Model
{
    protected $fillable = [
        'supplier_id',
        'name',
        'address',
        'region',
        'district',
        'ward',
        'latitude',
        'longitude',
        'phone',
        'is_primary',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'is_primary' => 'boolean',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
    public function inventories(): HasMany
{
    return $this->hasMany(SupplierInventory::class);
}
}