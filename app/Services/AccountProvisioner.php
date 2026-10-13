<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Creates accounts and guarantees every buyer/supplier has the business
 * profile the rest of the application depends on.
 */
class AccountProvisioner
{
    /**
     * @param  array{name: string, email: string, phone?: ?string, password: string, google_id?: ?string, avatar?: ?string}  $attributes
     * @param  array{business_name?: ?string, tin_number?: ?string}  $business
     */
    public function create(array $attributes, string $role, array $business = []): User
    {
        $role = in_array($role, User::SELF_SERVICE_ROLES, true) ? $role : 'buyer';

        return DB::transaction(function () use ($attributes, $role, $business) {
            $user = new User($attributes);
            $user->forceFill(['role' => $role])->save();

            $this->ensureProfile($user, $business);

            return $user;
        });
    }

    /**
     * Creates the profile a buyer or supplier is missing. Idempotent.
     * Suppliers always start "pending" until an administrator approves them.
     */
    public function ensureProfile(User $user, array $business = []): void
    {
        $businessName = trim((string) ($business['business_name'] ?? '')) ?: $user->name;

        if ($user->isBuyer() && ! $user->buyerProfile()->exists()) {
            $user->buyerProfile()->create(['business_name' => $businessName]);
            $user->unsetRelation('buyerProfile');
        }

        if ($user->isSupplier() && ! $user->supplier()->exists()) {
            $user->supplier()->create([
                'business_name' => $businessName,
                'tin_number' => ($business['tin_number'] ?? null) ?: null,
                'status' => 'pending',
            ]);
            $user->unsetRelation('supplier');
        }
    }
}
