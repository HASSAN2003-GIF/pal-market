<?php

namespace App\Policies;

use App\Models\BuyerRequest;
use App\Models\User;

/**
 * Buyers manage their own requests. Reading stays available to a suspended
 * buyer profile (their history), but every change requires an active profile.
 */
class BuyerRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->buyerProfile !== null;
    }

    public function view(User $user, BuyerRequest $buyerRequest): bool
    {
        return $this->owns($user, $buyerRequest);
    }

    public function create(User $user): bool
    {
        return $user->activeBuyerProfile() !== null;
    }

    public function update(User $user, BuyerRequest $buyerRequest): bool
    {
        return $this->ownsActively($user, $buyerRequest) && $buyerRequest->status === 'draft';
    }

    public function publish(User $user, BuyerRequest $buyerRequest): bool
    {
        return $this->update($user, $buyerRequest);
    }

    public function cancel(User $user, BuyerRequest $buyerRequest): bool
    {
        return $this->ownsActively($user, $buyerRequest)
            && in_array($buyerRequest->status, ['draft', 'open'], true);
    }

    public function delete(User $user, BuyerRequest $buyerRequest): bool
    {
        return $this->ownsActively($user, $buyerRequest) && $buyerRequest->status === 'draft';
    }

    private function owns(User $user, BuyerRequest $buyerRequest): bool
    {
        return $user->buyerProfile !== null
            && $user->buyerProfile->id === $buyerRequest->buyer_profile_id;
    }

    private function ownsActively(User $user, BuyerRequest $buyerRequest): bool
    {
        return $user->activeBuyerProfile()?->id === $buyerRequest->buyer_profile_id;
    }
}
