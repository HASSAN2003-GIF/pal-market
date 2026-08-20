<?php

namespace App\Policies;

use App\Models\BuyerRequest;
use App\Models\User;

class BuyerRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->buyerProfile !== null;
    }

    public function view(User $user, BuyerRequest $buyerRequest): bool
    {
        return $user->buyerProfile?->is($buyerRequest->buyerProfile) ?? false;
    }

    public function create(User $user): bool
    {
        return $user->buyerProfile !== null;
    }

    public function update(User $user, BuyerRequest $buyerRequest): bool
    {
        return (
            $user->buyerProfile?->is($buyerRequest->buyerProfile) ?? false
        ) && $buyerRequest->status === 'draft';
    }

    public function delete(User $user, BuyerRequest $buyerRequest): bool
    {
        return (
            $user->buyerProfile?->is($buyerRequest->buyerProfile) ?? false
        ) && $buyerRequest->status === 'draft';
    }
}
