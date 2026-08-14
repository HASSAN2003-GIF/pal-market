<?php

namespace App\Services;

use App\Models\BuyerRequest;
use Illuminate\Validation\ValidationException;

class BuyerRequestStatusService
{
    public function publish(
        BuyerRequest $request
    ): BuyerRequest {
        if ($request->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => 'Only draft buyer requests can be published.',
            ]);
        }

        $request->update([
            'status' => 'open',
        ]);

        return $request->refresh();
    }
    public function cancel(
    BuyerRequest $request
): BuyerRequest {
    if (! in_array($request->status, ['draft', 'open'], true)) {
        throw ValidationException::withMessages([
            'status' => 'Only draft or open buyer requests can be cancelled.',
        ]);
    }

    $request->update([
        'status' => 'cancelled',
    ]);

    return $request->refresh();
}
}