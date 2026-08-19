<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierInventory extends Model
{
    protected $fillable = [
        'supplier_product_id',
        'supplier_location_id',
        'quantity',
        'low_stock_threshold',
        'is_available',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'low_stock_threshold' => 'integer',
            'is_available' => 'boolean',
        ];
    }

    public function supplierProduct(): BelongsTo
    {
        return $this->belongsTo(SupplierProduct::class);
    }

    public function supplierLocation(): BelongsTo
    {
        return $this->belongsTo(SupplierLocation::class);
    }
}
