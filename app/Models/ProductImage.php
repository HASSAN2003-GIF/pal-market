<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductImage extends Model
{
    public const VARIANTS = ['thumb', 'large'];

    protected $fillable = [
        'product_id',
        'supplier_product_id',
        'uploaded_by',
        'disk',
        'directory',
        'extension',
        'width',
        'height',
        'bytes',
        'alt_text',
        'is_primary',
        'sort_order',
    ];

    protected $hidden = ['disk', 'directory', 'extension', 'uploaded_by', 'bytes'];

    protected $appends = ['urls'];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'width' => 'integer',
            'height' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function supplierProduct(): BelongsTo
    {
        return $this->belongsTo(SupplierProduct::class);
    }

    public function path(string $variant): string
    {
        return "{$this->directory}/{$variant}.{$this->extension}";
    }

    /**
     * Same-origin URLs served by MediaController, so the Content Security
     * Policy does not need to allow another host.
     */
    public function getUrlsAttribute(): array
    {
        return collect(self::VARIANTS)
            ->mapWithKeys(fn (string $variant) => [$variant => '/media/'.$this->path($variant)])
            ->all();
    }
}
