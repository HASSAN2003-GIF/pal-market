<?php

namespace App\Services;

use App\Models\PurchaseOrder;
use Illuminate\Validation\ValidationException;

class PurchaseOrderStatusService
{
    public function confirm(
        PurchaseOrder $purchaseOrder
    ): PurchaseOrder {
        $this->ensureStatus(
            $purchaseOrder,
            'pending',
            'Only pending purchase orders can be confirmed.'
        );

        $purchaseOrder->update([
            'status' => 'confirmed',
        ]);

        return $purchaseOrder->refresh();
    }

    public function process(
        PurchaseOrder $purchaseOrder
    ): PurchaseOrder {
        $this->ensureStatus(
            $purchaseOrder,
            'confirmed',
            'Only confirmed purchase orders can be processed.'
        );

        $purchaseOrder->update([
            'status' => 'processing',
        ]);

        return $purchaseOrder->refresh();
    }

    public function ship(
        PurchaseOrder $purchaseOrder
    ): PurchaseOrder {
        $this->ensureStatus(
            $purchaseOrder,
            'processing',
            'Only processing purchase orders can be shipped.'
        );

        $purchaseOrder->update([
            'status' => 'shipped',
        ]);

        return $purchaseOrder->refresh();
    }

    public function deliver(
        PurchaseOrder $purchaseOrder
    ): PurchaseOrder {
        $this->ensureStatus(
            $purchaseOrder,
            'shipped',
            'Only shipped purchase orders can be delivered.'
        );

        $purchaseOrder->update([
            'status' => 'delivered',
        ]);

        return $purchaseOrder->refresh();
    }

    public function complete(
        PurchaseOrder $purchaseOrder
    ): PurchaseOrder {
        $this->ensureStatus(
            $purchaseOrder,
            'delivered',
            'Only delivered purchase orders can be completed.'
        );

        $purchaseOrder->update([
            'status' => 'completed',
        ]);

        return $purchaseOrder->refresh();
    }

    public function cancel(
        PurchaseOrder $purchaseOrder
    ): PurchaseOrder {
        if (! in_array($purchaseOrder->status, [
            'pending',
            'confirmed',
            'processing',
        ], true)) {
            throw ValidationException::withMessages([
                'status' => 'Only pending, confirmed, or processing purchase orders can be cancelled.',
            ]);
        }

        $purchaseOrder->update([
            'status' => 'cancelled',
        ]);

        return $purchaseOrder->refresh();
    }

    private function ensureStatus(
        PurchaseOrder $purchaseOrder,
        string $expectedStatus,
        string $message
    ): void {
        if ($purchaseOrder->status !== $expectedStatus) {
            throw ValidationException::withMessages([
                'status' => $message,
            ]);
        }
    }
}
