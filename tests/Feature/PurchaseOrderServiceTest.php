<?php

namespace Tests\Feature;

use App\Models\BuyerProfile;
use App\Models\BuyerRequest;
use App\Models\BuyerRequestItem;
use App\Models\Category;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\SupplierQuotation;
use App\Models\SupplierQuotationItem;
use App\Models\User;
use App\Services\PurchaseOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PurchaseOrderServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_accepted_quotation_can_create_purchase_order(): void
    {
        $data = $this->createQuotationScenario();

        SupplierQuotationItem::create([
            'supplier_quotation_id' => $data['quotation']->id,
            'product_id' => $data['product']->id,
            'quantity' => 100,
            'unit' => 'bag',
            'unit_price' => 18000,
            'total_price' => 1800000,
        ]);

        $purchaseOrder = app(PurchaseOrderService::class)
            ->createFromQuotation($data['quotation']);

        $this->assertInstanceOf(
            PurchaseOrder::class,
            $purchaseOrder
        );

        $this->assertEquals(
            'pending',
            $purchaseOrder->status
        );

        $this->assertDatabaseHas('purchase_orders', [
            'id' => $purchaseOrder->id,
            'supplier_quotation_id' => $data['quotation']->id,
            'buyer_profile_id' => $data['buyer']->id,
            'supplier_id' => $data['supplier']->id,
            'status' => 'pending',
        ]);
    }

    public function test_non_accepted_quotation_cannot_create_purchase_order(): void
    {
        $data = $this->createQuotationScenario('submitted');

        $this->expectException(ValidationException::class);

        app(PurchaseOrderService::class)
            ->createFromQuotation($data['quotation']);
    }

    public function test_quotation_without_items_cannot_create_purchase_order(): void
    {
        $data = $this->createQuotationScenario('accepted');

        $this->expectException(ValidationException::class);

        app(PurchaseOrderService::class)
            ->createFromQuotation($data['quotation']);
    }

    public function test_quotation_cannot_create_two_purchase_orders(): void
    {
        $data = $this->createQuotationScenario('accepted');

        SupplierQuotationItem::create([
            'supplier_quotation_id' => $data['quotation']->id,
            'product_id' => $data['product']->id,
            'quantity' => 100,
            'unit' => 'bag',
            'unit_price' => 18000,
            'total_price' => 1800000,
        ]);

        $service = app(PurchaseOrderService::class);

        $service->createFromQuotation($data['quotation']);

        $this->expectException(ValidationException::class);

        $service->createFromQuotation($data['quotation']);
    }

    public function test_purchase_order_copies_quotation_totals(): void
    {
        $data = $this->createQuotationScenario('accepted');

        SupplierQuotationItem::create([
            'supplier_quotation_id' => $data['quotation']->id,
            'product_id' => $data['product']->id,
            'quantity' => 100,
            'unit' => 'bag',
            'unit_price' => 18000,
            'total_price' => 1800000,
        ]);

        $purchaseOrder = app(PurchaseOrderService::class)
            ->createFromQuotation($data['quotation']);

        $this->assertEquals(
            '1800000.00',
            $purchaseOrder->subtotal
        );

        $this->assertEquals(
            '50000.00',
            $purchaseOrder->delivery_fee
        );

        $this->assertEquals(
            '1850000.00',
            $purchaseOrder->total_amount
        );

        $this->assertEquals(
            'TZS',
            $purchaseOrder->currency
        );
    }

    public function test_purchase_order_copies_quotation_items(): void
    {
        $data = $this->createQuotationScenario('accepted');

        SupplierQuotationItem::create([
            'supplier_quotation_id' => $data['quotation']->id,
            'product_id' => $data['product']->id,
            'quantity' => 100,
            'unit' => 'bag',
            'unit_price' => 18000,
            'total_price' => 1800000,
            'notes' => 'Portland cement',
        ]);

        $purchaseOrder = app(PurchaseOrderService::class)
            ->createFromQuotation($data['quotation']);

        $this->assertDatabaseHas('purchase_order_items', [
            'purchase_order_id' => $purchaseOrder->id,
            'product_id' => $data['product']->id,
            'quantity' => 100,
            'unit' => 'bag',
            'unit_price' => 18000,
            'total_price' => 1800000,
            'notes' => 'Portland cement',
        ]);
    }

    private function createQuotationScenario(
        string $quotationStatus = 'accepted'
    ): array {
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
            'tin_number' => 'PO-TIN-001',
            'description' => 'Construction materials supplier',
            'status' => 'approved',
        ]);

        $request = BuyerRequest::create([
            'buyer_profile_id' => $buyer->id,
            'request_number' => 'REQ-PO-'.uniqid(),
            'title' => 'Cement requirement',
            'description' => 'Need cement.',
            'status' => 'closed',
        ]);

        $category = Category::create([
            'name' => 'Cement',
            'slug' => 'cement-'.uniqid(),
            'is_active' => true,
        ]);

        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Portland Cement',
            'slug' => 'portland-cement-'.uniqid(),
            'unit' => 'bag',
            'is_active' => true,
        ]);

        BuyerRequestItem::create([
            'buyer_request_id' => $request->id,
            'product_id' => $product->id,
            'quantity' => 100,
            'unit' => 'bag',
        ]);

        $quotation = SupplierQuotation::create([
            'buyer_request_id' => $request->id,
            'supplier_id' => $supplier->id,
            'quotation_number' => 'QUO-PO-'.uniqid(),
            'subtotal' => 1800000,
            'delivery_fee' => 50000,
            'total_amount' => 1850000,
            'currency' => 'TZS',
            'status' => $quotationStatus,
        ]);

        return compact(
            'buyer',
            'supplier',
            'request',
            'product',
            'quotation'
        );
    }
}
