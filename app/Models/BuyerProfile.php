<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BuyerProfile extends Model
{
    protected $fillable = [
        'user_id',
        'business_name',
        'business_type',
        'tin_number',
        'description',
        'status',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
    public function requests(): HasMany
{
    return $this->hasMany(BuyerRequest::class);
}
}