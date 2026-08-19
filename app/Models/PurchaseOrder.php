<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrder extends Model
{
    protected $fillable = [
        'buyer_profile_id',
        'supplier_id',
        'supplier_quotation_id',
        'order_number',
        'subtotal',
        'delivery_fee',
        'total_amount',
        'currency',
        'status',
        'notes',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'delivery_fee' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    public function buyerProfile(): BelongsTo
    {
        return $this->belongsTo(BuyerProfile::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function supplierQuotation(): BelongsTo
    {
        return $this->belongsTo(SupplierQuotation::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }
}
