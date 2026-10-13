<?php

namespace App\Support;

use App\Models\User;

/**
 * The single shape in which the API exposes the signed-in user, so the
 * frontend can route by role and know whether the business profile may trade.
 */
class UserPresenter
{
    public static function present(User $user): array
    {
        $user->loadMissing(['buyerProfile', 'supplier']);

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'role' => $user->role,
            'avatar' => $user->avatar,
            'email_verified_at' => $user->email_verified_at?->toIso8601String(),
            'google_linked' => $user->google_id !== null,
            'created_at' => $user->created_at?->toIso8601String(),
            'locale' => $user->locale,
            'region' => $user->region,
            'buyer_profile' => $user->buyerProfile ? [
                'id' => $user->buyerProfile->id,
                'business_name' => $user->buyerProfile->business_name,
                'business_type' => $user->buyerProfile->business_type,
                'tin_number' => $user->buyerProfile->tin_number,
                'description' => $user->buyerProfile->description,
                'status' => $user->buyerProfile->status,
            ] : null,
            'supplier' => $user->supplier ? [
                'id' => $user->supplier->id,
                'business_name' => $user->supplier->business_name,
                'tin_number' => $user->supplier->tin_number,
                'description' => $user->supplier->description,
                'status' => $user->supplier->status,
                'verified_at' => $user->supplier->verified_at?->toIso8601String(),
            ] : null,
        ];
    }
}
