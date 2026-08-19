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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PurchaseOrderApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_buyer_can_create_purchase_order_from_accepted_quotation(): void
    {
        $data = $this->createScenario();

        Sanctum::actingAs($data['buyerUser']);

        $response = $this->postJson(
            "/api/quotations/{$data['quotation']->id}/purchase-order"
        );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'purchase_order.status',
                'pending'
            );

        $this->assertDatabaseHas('purchase_orders', [
            'supplier_quotation_id' => $data['quotation']->id,
            'buyer_profile_id' => $data['buyer']->id,
            'supplier_id' => $data['supplier']->id,
            'status' => 'pending',
        ]);
    }

    public function test_supplier_cannot_create_purchase_order(): void
    {
        $data = $this->createScenario();

        Sanctum::actingAs($data['supplierUser']);

        $this->postJson(
            "/api/quotations/{$data['quotation']->id}/purchase-order"
        )->assertForbidden();
    }

    public function test_buyer_can_view_own_purchase_order(): void
    {
        $data = $this->createScenario();

        $purchaseOrder = app(\App\Services\PurchaseOrderService::class)
            ->createFromQuotation($data['quotation']);

        Sanctum::actingAs($data['buyerUser']);

        $this->getJson(
            "/api/purchase-orders/{$purchaseOrder->id}"
        )
            ->assertOk()
            ->assertJsonPath(
                'purchase_order.id',
                $purchaseOrder->id
            );
    }

    public function test_other_buyer_cannot_view_purchase_order(): void
    {
        $data = $this->createScenario();

        $purchaseOrder = app(\App\Services\PurchaseOrderService::class)
            ->createFromQuotation($data['quotation']);

        $otherUser = User::factory()->create();

        BuyerProfile::create([
            'user_id' => $otherUser->id,
            'business_name' => 'Other Hardware',
            'business_type' => 'Hardware Store',
            'status' => 'active',
        ]);

        Sanctum::actingAs($otherUser);

        $this->getJson(
            "/api/purchase-orders/{$purchaseOrder->id}"
        )->assertForbidden();
    }

    public function test_supplier_can_confirm_purchase_order(): void
    {
        $data = $this->createScenario();

        $purchaseOrder = app(\App\Services\PurchaseOrderService::class)
            ->createFromQuotation($data['quotation']);

        Sanctum::actingAs($data['supplierUser']);

        $this->postJson(
            "/api/purchase-orders/{$purchaseOrder->id}/confirm"
        )
            ->assertOk()
            ->assertJsonPath(
                'purchase_order.status',
                'confirmed'
            );
    }

    public function test_buyer_cannot_confirm_purchase_order(): void
    {
        $data = $this->createScenario();

        $purchaseOrder = app(\App\Services\PurchaseOrderService::class)
            ->createFromQuotation($data['quotation']);

        Sanctum::actingAs($data['buyerUser']);

        $this->postJson(
            "/api/purchase-orders/{$purchaseOrder->id}/confirm"
        )->assertForbidden();
    }

    public function test_supplier_can_process_purchase_order(): void
    {
        $data = $this->createScenario();

        $purchaseOrder = app(\App\Services\PurchaseOrderService::class)
            ->createFromQuotation($data['quotation']);

        $purchaseOrder->update([
            'status' => 'confirmed',
        ]);

        Sanctum::actingAs($data['supplierUser']);

        $this->postJson(
            "/api/purchase-orders/{$purchaseOrder->id}/process"
        )
            ->assertOk()
            ->assertJsonPath(
                'purchase_order.status',
                'processing'
            );
    }

    public function test_supplier_can_ship_purchase_order(): void
    {
        $data = $this->createScenario();

        $purchaseOrder = app(\App\Services\PurchaseOrderService::class)
            ->createFromQuotation($data['quotation']);

        $purchaseOrder->update([
            'status' => 'processing',
        ]);

        Sanctum::actingAs($data['supplierUser']);

        $this->postJson(
            "/api/purchase-orders/{$purchaseOrder->id}/ship"
        )
            ->assertOk()
            ->assertJsonPath(
                'purchase_order.status',
                'shipped'
            );
    }

    public function test_buyer_can_deliver_purchase_order(): void
    {
        $data = $this->createScenario();

        $purchaseOrder = app(\App\Services\PurchaseOrderService::class)
            ->createFromQuotation($data['quotation']);

        $purchaseOrder->update([
            'status' => 'shipped',
        ]);

        Sanctum::actingAs($data['buyerUser']);

        $this->postJson(
            "/api/purchase-orders/{$purchaseOrder->id}/deliver"
        )
            ->assertOk()
            ->assertJsonPath(
                'purchase_order.status',
                'delivered'
            );
    }

    public function test_buyer_can_complete_purchase_order(): void
    {
        $data = $this->createScenario();

        $purchaseOrder = app(\App\Services\PurchaseOrderService::class)
            ->createFromQuotation($data['quotation']);

        $purchaseOrder->update([
            'status' => 'delivered',
        ]);

        Sanctum::actingAs($data['buyerUser']);

        $this->postJson(
            "/api/purchase-orders/{$purchaseOrder->id}/complete"
        )
            ->assertOk()
            ->assertJsonPath(
                'purchase_order.status',
                'completed'
            );
    }

    public function test_buyer_can_cancel_purchase_order(): void
    {
        $data = $this->createScenario();

        $purchaseOrder = app(\App\Services\PurchaseOrderService::class)
            ->createFromQuotation($data['quotation']);

        Sanctum::actingAs($data['buyerUser']);

        $this->postJson(
            "/api/purchase-orders/{$purchaseOrder->id}/cancel"
        )
            ->assertOk()
            ->assertJsonPath(
                'purchase_order.status',
                'cancelled'
            );
    }

    private function createScenario(): array
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
            'tin_number' => 'API-PO-TIN-001',
            'description' => 'Construction materials supplier',
            'status' => 'approved',
        ]);

        $request = BuyerRequest::create([
            'buyer_profile_id' => $buyer->id,
            'request_number' => 'REQ-API-PO-'.uniqid(),
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
            'quotation_number' => 'QUO-API-PO-'.uniqid(),
            'subtotal' => 1800000,
            'delivery_fee' => 50000,
            'total_amount' => 1850000,
            'currency' => 'TZS',
            'status' => 'accepted',
        ]);

        SupplierQuotationItem::create([
            'supplier_quotation_id' => $quotation->id,
            'product_id' => $product->id,
            'quantity' => 100,
            'unit' => 'bag',
            'unit_price' => 18000,
            'total_price' => 1800000,
        ]);

        return compact(
            'buyerUser',
            'supplierUser',
            'buyer',
            'supplier',
            'request',
            'product',
            'quotation'
        );
    }
}