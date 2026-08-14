<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplierQuotation extends Model
{
    protected $fillable = [
        'buyer_request_id',
        'supplier_id',
        'quotation_number',
        'subtotal',
        'delivery_fee',
        'total_amount',
        'currency',
        'status',
        'valid_until',
        'notes',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'delivery_fee' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'valid_until' => 'datetime',
    ];

    public function buyerRequest(): BelongsTo
    {
        return $this->belongsTo(BuyerRequest::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SupplierQuotationItem::class);
    }
}