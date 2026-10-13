<?php

namespace App\Services;

use App\Models\BuyerRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BuyerRequestStatusService
{
    /** Default quoting window when the buyer does not choose one. */
    public const DEFAULT_OPEN_DAYS = 14;

    public const MAX_OPEN_DAYS = 60;

    public function publish(BuyerRequest $request, ?int $openForDays = null): BuyerRequest
    {
        if ($request->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => 'Only draft buyer requests can be published.',
            ]);
        }

        if (! $request->items()->exists()) {
            throw ValidationException::withMessages([
                'items' => 'Add at least one item before publishing the request to suppliers.',
            ]);
        }

        $days = min(max($openForDays ?? self::DEFAULT_OPEN_DAYS, 1), self::MAX_OPEN_DAYS);

        $request->update([
            'status' => 'open',
            'published_at' => now(),
            'expires_at' => now()->addDays($days),
        ]);

        return $request->refresh();
    }

    /**
     * Cancelling withdraws the request from the market; any quotation still
     * in play is marked cancelled so suppliers see the outcome.
     */
    public function cancel(BuyerRequest $request): BuyerRequest
    {
        if (! in_array($request->status, ['draft', 'open'], true)) {
            throw ValidationException::withMessages([
                'status' => 'Only draft or open buyer requests can be cancelled.',
            ]);
        }

        return DB::transaction(function () use ($request) {
            $request->update(['status' => 'cancelled']);

            $request->quotations()
                ->whereIn('status', ['draft', 'submitted'])
                ->update(['status' => 'cancelled']);

            return $request->refresh();
        });
    }

    public function expire(BuyerRequest $request): BuyerRequest
    {
        if ($request->status !== 'open') {
            throw ValidationException::withMessages([
                'status' => 'Only open buyer requests can expire.',
            ]);
        }

        if ($request->expires_at === null || $request->expires_at->isFuture()) {
            throw ValidationException::withMessages([
                'status' => 'Only buyer requests whose expiration time has passed can expire.',
            ]);
        }

        $request->update(['status' => 'expired']);

        return $request->refresh();
    }
}
