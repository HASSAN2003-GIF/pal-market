<?php

namespace Tests\Feature;

use App\Models\BuyerProfile;
use App\Models\BuyerRequest;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\SupplierQuotation;
use App\Models\User;
use App\Services\PurchaseOrderStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PurchaseOrderStatusServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_purchase_order_can_be_confirmed(): void
    {
        $purchaseOrder = $this->createPurchaseOrder('pending');

        $purchaseOrder = app(PurchaseOrderStatusService::class)
            ->confirm($purchaseOrder);

        $this->assertEquals('confirmed', $purchaseOrder->status);
    }

    public function test_confirmed_purchase_order_can_be_processed(): void
    {
        $purchaseOrder = $this->createPurchaseOrder('confirmed');

        $purchaseOrder = app(PurchaseOrderStatusService::class)
            ->process($purchaseOrder);

        $this->assertEquals('processing', $purchaseOrder->status);
    }

    public function test_processing_purchase_order_can_be_shipped(): void
    {
        $purchaseOrder = $this->createPurchaseOrder('processing');

        $purchaseOrder = app(PurchaseOrderStatusService::class)
            ->ship($purchaseOrder);

        $this->assertEquals('shipped', $purchaseOrder->status);
    }

    public function test_shipped_purchase_order_can_be_delivered(): void
    {
        $purchaseOrder = $this->createPurchaseOrder('shipped');

        $purchaseOrder = app(PurchaseOrderStatusService::class)
            ->deliver($purchaseOrder);

        $this->assertEquals('delivered', $purchaseOrder->status);
    }

    public function test_delivered_purchase_order_can_be_completed(): void
    {
        $purchaseOrder = $this->createPurchaseOrder('delivered');

        $purchaseOrder = app(PurchaseOrderStatusService::class)
            ->complete($purchaseOrder);

        $this->assertEquals('completed', $purchaseOrder->status);
    }

    public function test_pending_purchase_order_can_be_cancelled(): void
    {
        $purchaseOrder = $this->createPurchaseOrder('pending');

        $purchaseOrder = app(PurchaseOrderStatusService::class)
            ->cancel($purchaseOrder);

        $this->assertEquals('cancelled', $purchaseOrder->status);
    }

    public function test_confirmed_purchase_order_can_be_cancelled(): void
    {
        $purchaseOrder = $this->createPurchaseOrder('confirmed');

        $purchaseOrder = app(PurchaseOrderStatusService::class)
            ->cancel($purchaseOrder);

        $this->assertEquals('cancelled', $purchaseOrder->status);
    }

    public function test_processing_purchase_order_can_be_cancelled(): void
    {
        $purchaseOrder = $this->createPurchaseOrder('processing');

        $purchaseOrder = app(PurchaseOrderStatusService::class)
            ->cancel($purchaseOrder);

        $this->assertEquals('cancelled', $purchaseOrder->status);
    }

    public function test_pending_purchase_order_cannot_be_shipped_directly(): void
    {
        $purchaseOrder = $this->createPurchaseOrder('pending');

        $this->expectException(ValidationException::class);

        app(PurchaseOrderStatusService::class)
            ->ship($purchaseOrder);
    }

    public function test_completed_purchase_order_cannot_be_cancelled(): void
    {
        $purchaseOrder = $this->createPurchaseOrder('completed');

        $this->expectException(ValidationException::class);

        app(PurchaseOrderStatusService::class)
            ->cancel($purchaseOrder);
    }

    public function test_cancelled_purchase_order_cannot_be_confirmed(): void
    {
        $purchaseOrder = $this->createPurchaseOrder('cancelled');

        $this->expectException(ValidationException::class);

        app(PurchaseOrderStatusService::class)
            ->confirm($purchaseOrder);
    }

    private function createPurchaseOrder(
        string $status
    ): PurchaseOrder {
        $buyerUser = User::factory()->create();

        $buyer = BuyerProfile::create([
            'user_id' => $buyerUser->id,
            'business_name' => 'Hassan Hardware',
            'business_type' => 'Hardware Store',
            'status' => 'active',
        ]);

        $supplierUser = User::factory()->create();

        $supplier = Supplier::create([
            'user_id' => $supplierUser->id,
            'business_name' => 'Tanzania Building Supplies',
            'tin_number' => 'PO-STATUS-'.uniqid(),
            'description' => 'Construction materials supplier',
            'status' => 'approved',
        ]);

        $request = BuyerRequest::create([
            'buyer_profile_id' => $buyer->id,
            'request_number' => 'REQ-STATUS-'.uniqid(),
            'title' => 'Cement requirement',
            'description' => 'Need cement.',
            'status' => 'closed',
        ]);

        $quotation = SupplierQuotation::create([
            'buyer_request_id' => $request->id,
            'supplier_id' => $supplier->id,
            'quotation_number' => 'QUO-STATUS-'.uniqid(),
            'subtotal' => 1800000,
            'delivery_fee' => 50000,
            'total_amount' => 1850000,
            'currency' => 'TZS',
            'status' => 'accepted',
        ]);

        return PurchaseOrder::create([
            'buyer_profile_id' => $buyer->id,
            'supplier_id' => $supplier->id,
            'supplier_quotation_id' => $quotation->id,
            'order_number' => 'PO-STATUS-'.uniqid(),
            'subtotal' => 1800000,
            'delivery_fee' => 50000,
            'total_amount' => 1850000,
            'currency' => 'TZS',
            'status' => $status,
        ]);
    }
}
