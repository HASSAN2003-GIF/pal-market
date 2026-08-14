<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BuyerRequest extends Model
{
    protected $fillable = [
        'buyer_profile_id',
        'request_number',
        'title',
        'description',
        'status',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public function buyerProfile(): BelongsTo
    {
        return $this->belongsTo(BuyerProfile::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(BuyerRequestItem::class);
    }
    public function quotations(): HasMany
{
    return $this->hasMany(SupplierQuotation::class);
}
}