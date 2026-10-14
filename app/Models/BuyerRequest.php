<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BuyerRequest extends Model
{
    public const STATUSES = ['draft', 'open', 'closed', 'cancelled', 'expired'];

    protected $fillable = [
        'buyer_profile_id',
        'request_number',
        'title',
        'description',
        'delivery_region',
        'delivery_address',
        'needed_by',
        'status',
        'published_at',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'published_at' => 'datetime',
        'needed_by' => 'date:Y-m-d',
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

    /**
     * Quotations the buyer is allowed to see: suppliers' drafts stay private.
     */
    public function visibleQuotations(): HasMany
    {
        return $this->quotations()->where('status', '!=', 'draft');
    }

    /**
     * Requests suppliers can currently quote on.
     */
    public function scopeOpenForQuotes(Builder $query): Builder
    {
        return $query
            ->where('status', 'open')
            ->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    public function isOpenForQuotes(): bool
    {
        return $this->status === 'open'
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }
}
