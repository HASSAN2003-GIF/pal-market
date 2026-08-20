<?php

namespace App\Services;

use App\Models\BuyerRequest;
use App\Models\BuyerRequestItem;
use App\Models\Product;
use Illuminate\Validation\ValidationException;

class BuyerRequestService
{
    public function create(
        int $buyerProfileId,
        string $title,
        ?string $description = null
    ): BuyerRequest {
        return BuyerRequest::create([
            'buyer_profile_id' => $buyerProfileId,
            'request_number' => 'REQ-'.now()->format('YmdHis').'-'.uniqid(),
            'title' => $title,
            'description' => $description,
            'status' => 'draft',
        ]);
    }

    public function update(
        BuyerRequest $buyerRequest,
        string $title,
        ?string $description = null
    ): BuyerRequest {
        if ($buyerRequest->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => 'Only draft buyer requests can be updated.',
            ]);
        }

        $buyerRequest->update([
            'title' => $title,
            'description' => $description,
        ]);

        return $buyerRequest->refresh();
    }

    public function addItem(
        BuyerRequest $buyerRequest,
        int $productId,
        int $quantity,
        string $unit,
        ?string $notes = null
    ): BuyerRequestItem {
        if ($buyerRequest->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => 'Only draft buyer requests can have items added.',
            ]);
        }

        if ($quantity <= 0) {
            throw ValidationException::withMessages([
                'quantity' => 'Quantity must be greater than zero.',
            ]);
        }

        $productExists = Product::query()
            ->whereKey($productId)
            ->exists();

        if (! $productExists) {
            throw ValidationException::withMessages([
                'product_id' => 'The selected product does not exist.',
            ]);
        }

        $alreadyExists = $buyerRequest
            ->items()
            ->where('product_id', $productId)
            ->exists();

        if ($alreadyExists) {
            throw ValidationException::withMessages([
                'product_id' => 'This product has already been added to the buyer request.',
            ]);
        }

        return $buyerRequest->items()->create([
            'product_id' => $productId,
            'quantity' => $quantity,
            'unit' => $unit,
            'notes' => $notes,
        ]);
    }
}