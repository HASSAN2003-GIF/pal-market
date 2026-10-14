<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One issued version of a purchase order document. The snapshot is the
 * complete content of the document and is never edited after creation.
 */
class OrderDocument extends Model
{
    protected $fillable = [
        'purchase_order_id',
        'version',
        'snapshot',
        'fingerprint',
        'reason',
        'generated_by',
    ];

    protected $hidden = ['snapshot'];

    protected function casts(): array
    {
        return [
            'snapshot' => 'array',
            'version' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => false);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function reference(): string
    {
        return $this->snapshot['order']['number'].'-D'.$this->version;
    }
}
