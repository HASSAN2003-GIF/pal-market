<?php

namespace App\Models;

use App\Services\ProductSearch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductAlias extends Model
{
    protected $fillable = ['product_id', 'alias'];

    protected static function booted(): void
    {
        static::saved(fn () => ProductSearch::flush());
        static::deleted(fn () => ProductSearch::flush());
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
