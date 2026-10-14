<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/*
 * "role", "suspended_at" and the verification timestamps are deliberately not
 * mass assignable: they change only through explicit forceFill() calls in
 * code paths that have already authorized the change.
 */
#[Fillable([
    'name',
    'email',
    'phone',
    'password',
    'google_id',
    'avatar',
])]
#[Hidden([
    'password',
    'remember_token',
    'google_id',
    'two_factor_secret',
    'two_factor_recovery_codes',
])]
class User extends Authenticatable implements HasLocalePreference
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    public const ROLES = ['admin', 'buyer', 'supplier'];

    public const SELF_SERVICE_ROLES = ['buyer', 'supplier'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'suspended_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /** Language for emails and notifications; null uses the current request's language. */
    public function preferredLocale(): ?string
    {
        return $this->locale;
    }

    public function supplier(): HasOne
    {
        return $this->hasOne(Supplier::class);
    }

    public function buyerProfile(): HasOne
    {
        return $this->hasOne(BuyerProfile::class);
    }

    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_confirmed_at !== null && $this->two_factor_secret !== null;
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isBuyer(): bool
    {
        return $this->role === 'buyer';
    }

    public function isSupplier(): bool
    {
        return $this->role === 'supplier';
    }

    public function isSuspended(): bool
    {
        return $this->suspended_at !== null;
    }

    public function isEmailVerified(): bool
    {
        return $this->email_verified_at !== null;
    }

    public function isPhoneVerified(): bool
    {
        return $this->phone_verified_at !== null;
    }

    public function isVerified(): bool
    {
        return $this->isEmailVerified() || $this->isPhoneVerified();
    }

    /**
     * The supplier profile, but only while it is approved to trade.
     */
    public function activeSupplier(): ?Supplier
    {
        $supplier = $this->supplier;

        return $supplier?->status === 'approved' ? $supplier : null;
    }

    /**
     * The buyer profile, but only while it is allowed to trade.
     */
    public function activeBuyerProfile(): ?BuyerProfile
    {
        $profile = $this->buyerProfile;

        return $profile?->status === 'active' ? $profile : null;
    }
}
