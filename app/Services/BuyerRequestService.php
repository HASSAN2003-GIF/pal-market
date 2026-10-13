<?php

namespace App\Services;

use App\Models\BuyerRequest;
use App\Models\BuyerRequestItem;
use App\Models\Product;
use App\Support\DocumentNumber;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BuyerRequestService
{
    private const DETAIL_FIELDS = ['delivery_region', 'delivery_address', 'needed_by'];

    /**
     * @param  array{delivery_region?: ?string, delivery_address?: ?string, needed_by?: ?string, items?: array<int, array{product_id: int, quantity: int, unit?: ?string, notes?: ?string}>}  $details
     */
    public function create(
        int $buyerProfileId,
        string $title,
        ?string $description = null,
        array $details = [],
    ): BuyerRequest {
        return DB::transaction(function () use ($buyerProfileId, $title, $description, $details) {
            $buyerRequest = BuyerRequest::create([
                'buyer_profile_id' => $buyerProfileId,
                'request_number' => DocumentNumber::make('REQ'),
                'title' => $title,
                'description' => $description,
                ...array_intersect_key($details, array_flip(self::DETAIL_FIELDS)),
                'status' => 'draft',
            ]);

            foreach ($details['items'] ?? [] as $item) {
                $this->addItem(
                    $buyerRequest,
                    (int) $item['product_id'],
                    (int) $item['quantity'],
                    $item['unit'] ?? null,
                    $item['notes'] ?? null,
                );
            }

            return $buyerRequest;
        });
    }

    public function update(
        BuyerRequest $buyerRequest,
        string $title,
        ?string $description = null,
        array $details = [],
    ): BuyerRequest {
        $this->ensureDraft($buyerRequest, 'Only draft buyer requests can be updated.');

        $buyerRequest->update([
            'title' => $title,
            'description' => $description,
            ...array_intersect_key($details, array_flip(self::DETAIL_FIELDS)),
        ]);

        return $buyerRequest->refresh();
    }

    public function addItem(
        BuyerRequest $buyerRequest,
        int $productId,
        int $quantity,
        ?string $unit = null,
        ?string $notes = null
    ): BuyerRequestItem {
        $this->ensureDraft($buyerRequest, 'Only draft buyer requests can have items added.');
        $this->ensurePositive($quantity);

        $product = Product::query()->whereKey($productId)->where('is_active', true)->first();

        if (! $product) {
            throw ValidationException::withMessages([
                'product_id' => 'The selected product does not exist or is no longer available.',
            ]);
        }

        if ($buyerRequest->items()->where('product_id', $productId)->exists()) {
            throw ValidationException::withMessages([
                'product_id' => 'This product has already been added to the buyer request.',
            ]);
        }

        return $buyerRequest->items()->create([
            'product_id' => $productId,
            'quantity' => $quantity,
            'unit' => $unit ?: $product->unit,
            'notes' => $notes,
        ]);
    }

    public function updateItem(BuyerRequestItem $item, int $quantity, ?string $unit, ?string $notes): BuyerRequestItem
    {
        $this->ensureDraft($item->buyerRequest, 'Only items on draft buyer requests can be changed.');
        $this->ensurePositive($quantity);

        $item->update([
            'quantity' => $quantity,
            'unit' => $unit ?: $item->unit,
            'notes' => $notes,
        ]);

        return $item->refresh();
    }

    public function removeItem(BuyerRequestItem $item): void
    {
        $this->ensureDraft($item->buyerRequest, 'Only items on draft buyer requests can be removed.');

        $item->delete();
    }

    public function delete(BuyerRequest $buyerRequest): void
    {
        $this->ensureDraft($buyerRequest, 'Only draft buyer requests can be deleted.');

        $buyerRequest->delete();
    }

    private function ensureDraft(BuyerRequest $buyerRequest, string $message): void
    {
        if ($buyerRequest->status !== 'draft') {
            throw ValidationException::withMessages(['status' => $message]);
        }
    }

    private function ensurePositive(int $quantity): void
    {
        if ($quantity <= 0) {
            throw ValidationException::withMessages([
                'quantity' => 'Quantity must be greater than zero.',
            ]);
        }
    }
}
