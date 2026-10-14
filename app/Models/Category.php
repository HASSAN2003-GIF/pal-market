<?php

namespace App\Models;

use App\Services\ProductSearch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn () => ProductSearch::flush());
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
