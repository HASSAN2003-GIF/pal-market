<?php

namespace Tests\Feature;

use App\Models\BuyerProfile;
use App\Models\BuyerRequest;
use App\Models\Category;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\SupplierQuotation;
use App\Models\SupplierQuotationItem;
use App\Models\User;
use App\Policies\PurchaseOrderPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseOrderPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_buyer_can_view_own_purchase_order(): void
    {
        $data = $this->createPurchaseOrderScenario();

        $this->assertTrue(
            app(PurchaseOrderPolicy::class)->view(
                $data['buyerUser'],
                $data['purchaseOrder']
            )
        );
    }

    public function test_supplier_can_view_own_purchase_order(): void
    {
        $data = $this->createPurchaseOrderScenario();

        $this->assertTrue(
            app(PurchaseOrderPolicy::class)->view(
                $data['supplierUser'],
                $data['purchaseOrder']
            )
        );
    }

    public function test_other_buyer_cannot_view_purchase_order(): void
    {
        $data = $this->createPurchaseOrderScenario();

        $otherUser = User::factory()->create();

        BuyerProfile::create([
            'user_id' => $otherUser->id,
            'business_name' => 'Other Hardware',
            'business_type' => 'Hardware Store',
            'status' => 'active',
        ]);

        $this->assertFalse(
            app(PurchaseOrderPolicy::class)->view(
                $otherUser,
                $data['purchaseOrder']
            )
        );
    }

    public function test_other_supplier_cannot_view_purchase_order(): void
    {
        $data = $this->createPurchaseOrderScenario();

        $otherUser = User::factory()->create();

        Supplier::create([
            'user_id' => $otherUser->id,
            'business_name' => 'Other Supplier',
            'tin_number' => 'OTHER-TIN-001',
            'description' => 'Other supplier',
            'status' => 'approved',
        ]);

        $this->assertFalse(
            app(PurchaseOrderPolicy::class)->view(
                $otherUser,
                $data['purchaseOrder']
            )
        );
    }

    public function test_buyer_can_create_purchase_order(): void
    {
        $data = $this->createPurchaseOrderScenario();

        $this->assertTrue(
            app(PurchaseOrderPolicy::class)->create($data['buyerUser'])
        );
    }

    public function test_supplier_cannot_create_purchase_order(): void
    {
        $data = $this->createPurchaseOrderScenario();

        $this->assertFalse(
            app(PurchaseOrderPolicy::class)->create($data['supplierUser'])
        );
    }

    public function test_supplier_can_confirm_purchase_order(): void
    {
        $data = $this->createPurchaseOrderScenario();

        $this->assertTrue(
            app(PurchaseOrderPolicy::class)->confirm(
                $data['supplierUser'],
                $data['purchaseOrder']
            )
        );
    }

    public function test_buyer_cannot_confirm_purchase_order(): void
    {
        $data = $this->createPurchaseOrderScenario();

        $this->assertFalse(
            app(PurchaseOrderPolicy::class)->confirm(
                $data['buyerUser'],
                $data['purchaseOrder']
            )
        );
    }

    public function test_supplier_can_process_purchase_order(): void
    {
        $data = $this->createPurchaseOrderScenario();

        $this->assertTrue(
            app(PurchaseOrderPolicy::class)->process(
                $data['supplierUser'],
                $data['purchaseOrder']
            )
        );
    }

    public function test_buyer_cannot_process_purchase_order(): void
    {
        $data = $this->createPurchaseOrderScenario();

        $this->assertFalse(
            app(PurchaseOrderPolicy::class)->process(
                $data['buyerUser'],
                $data['purchaseOrder']
            )
        );
    }

    public function test_supplier_can_ship_purchase_order(): void
    {
        $data = $this->createPurchaseOrderScenario();

        $this->assertTrue(
            app(PurchaseOrderPolicy::class)->ship(
                $data['supplierUser'],
                $data['purchaseOrder']
            )
        );
    }

    public function test_buyer_cannot_ship_purchase_order(): void
    {
        $data = $this->createPurchaseOrderScenario();

        $this->assertFalse(
            app(PurchaseOrderPolicy::class)->ship(
                $data['buyerUser'],
                $data['purchaseOrder']
            )
        );
    }

    public function test_buyer_can_deliver_purchase_order(): void
    {
        $data = $this->createPurchaseOrderScenario();

        $this->assertTrue(
            app(PurchaseOrderPolicy::class)->deliver(
                $data['buyerUser'],
                $data['purchaseOrder']
            )
        );
    }

    public function test_supplier_cannot_deliver_purchase_order(): void
    {
        $data = $this->createPurchaseOrderScenario();

        $this->assertFalse(
            app(PurchaseOrderPolicy::class)->deliver(
                $data['supplierUser'],
                $data['purchaseOrder']
            )
        );
    }

    public function test_buyer_can_complete_purchase_order(): void
    {
        $data = $this->createPurchaseOrderScenario();

        $this->assertTrue(
            app(PurchaseOrderPolicy::class)->complete(
                $data['buyerUser'],
                $data['purchaseOrder']
            )
        );
    }

    public function test_supplier_cannot_complete_purchase_order(): void
    {
        $data = $this->createPurchaseOrderScenario();

        $this->assertFalse(
            app(PurchaseOrderPolicy::class)->complete(
                $data['supplierUser'],
                $data['purchaseOrder']
            )
        );
    }

    public function test_buyer_can_cancel_purchase_order(): void
    {
        $data = $this->createPurchaseOrderScenario();

        $this->assertTrue(
            app(PurchaseOrderPolicy::class)->cancel(
                $data['buyerUser'],
                $data['purchaseOrder']
            )
        );
    }

    public function test_supplier_cannot_cancel_purchase_order(): void
    {
        $data = $this->createPurchaseOrderScenario();

        $this->assertFalse(
            app(PurchaseOrderPolicy::class)->cancel(
                $data['supplierUser'],
                $data['purchaseOrder']
            )
        );
    }

    public function test_user_without_profile_cannot_view_purchase_order(): void
    {
        $data = $this->createPurchaseOrderScenario();

        $user = User::factory()->create();

        $this->assertFalse(
            app(PurchaseOrderPolicy::class)->view(
                $user,
                $data['purchaseOrder']
            )
        );
    }

    private function createPurchaseOrderScenario(): array
    {
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
            'tin_number' => 'PO-POLICY-TIN-001',
            'description' => 'Construction materials supplier',
            'status' => 'approved',
        ]);

        $request = BuyerRequest::create([
            'buyer_profile_id' => $buyer->id,
            'request_number' => 'REQ-POLICY-'.uniqid(),
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

        SupplierQuotation::create([
            'buyer_request_id' => $request->id,
            'supplier_id' => $supplier->id,
            'quotation_number' => 'QUO-POLICY-'.uniqid(),
            'subtotal' => 1800000,
            'delivery_fee' => 50000,
            'total_amount' => 1850000,
            'currency' => 'TZS',
            'status' => 'accepted',
        ]);

        $quotation = SupplierQuotation::latest('id')->first();

        SupplierQuotationItem::create([
            'supplier_quotation_id' => $quotation->id,
            'product_id' => $product->id,
            'quantity' => 100,
            'unit' => 'bag',
            'unit_price' => 18000,
            'total_price' => 1800000,
        ]);

        $purchaseOrder = PurchaseOrder::create([
            'buyer_profile_id' => $buyer->id,
            'supplier_id' => $supplier->id,
            'supplier_quotation_id' => $quotation->id,
            'order_number' => 'PO-POLICY-'.uniqid(),
            'subtotal' => 1800000,
            'delivery_fee' => 50000,
            'total_amount' => 1850000,
            'currency' => 'TZS',
            'status' => 'pending',
        ]);

        return compact(
            'buyerUser',
            'supplierUser',
            'buyer',
            'supplier',
            'quotation',
            'purchaseOrder'
        );
    }
}
