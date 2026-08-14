<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Supplier extends Model
{
    protected $fillable = [
        'user_id',
        'business_name',
        'tin_number',
        'description',
        'status',
        'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'verified_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function locations(): HasMany
{
    return $this->hasMany(SupplierLocation::class);
}

public function supplierProducts(): HasMany
{
    return $this->hasMany(SupplierProduct::class);
}

public function quotations(): HasMany
{
    return $this->hasMany(SupplierQuotation::class);
}

public function products(): BelongsToMany
{
    return $this->belongsToMany(
        Product::class,
        'supplier_products'
    )->withPivot([
        'supplier_sku',
        'description',
        'is_active',
    ]);
}
}