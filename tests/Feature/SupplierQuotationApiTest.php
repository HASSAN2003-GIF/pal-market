<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\BuyerProfile;
use App\Models\BuyerRequest;
use App\Models\BuyerRequestItem;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\SupplierQuotation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SupplierQuotationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_create_quotation(): void
    {
        $request = BuyerRequest::create([
            'buyer_profile_id' => $this->createBuyer()->id,
            'request_number' => 'REQ-API-001',
            'title' => 'Cement requirement',
            'description' => 'Need cement.',
            'status' => 'open',
        ]);

        $this->postJson(
            "/api/buyer-requests/{$request->id}/quotations"
        )->assertUnauthorized();
    }

    public function test_unauthenticated_user_cannot_view_quotation(): void
    {
        $data = $this->createQuotationScenario();
        $quotation = $data['quotation'];

        $response = $this->getJson(
            "/api/quotations/{$quotation->id}"
        );

        $response
            ->assertStatus(401);
    }

    public function test_supplier_can_view_own_quotation(): void
    {
        $data = $this->createQuotationScenario();
        $quotation = $data['quotation'];

        $response = $this
            ->actingAs($quotation->supplier->user)
            ->getJson("/api/quotations/{$quotation->id}");

        $response
            ->assertOk()
            ->assertJsonPath(
                'quotation.id',
                $quotation->id
            );
    }

    public function test_buyer_can_view_quotation_for_their_request(): void
    {
        $data = $this->createQuotationScenario();
        $quotation = $data['quotation'];

        $response = $this
            ->actingAs($quotation->buyerRequest->buyerProfile->user)
            ->getJson("/api/quotations/{$quotation->id}");

        $response
            ->assertOk()
            ->assertJsonPath(
                'quotation.id',
                $quotation->id
            );
    }

    public function test_supplier_cannot_view_another_suppliers_quotation(): void
    {
        $data = $this->createQuotationScenario();
        $quotation = $data['quotation'];

        $otherSupplierUser = User::factory()->create();

        Supplier::create([
            'user_id' => $otherSupplierUser->id,
            'business_name' => 'Another Supplier',
            'tin_number' => '888888888',
            'description' => 'Another supplier',
            'status' => 'approved',
        ]);

        $response = $this
            ->actingAs($otherSupplierUser)
            ->getJson("/api/quotations/{$quotation->id}");

        $response
            ->assertForbidden();
    }

    public function test_buyer_cannot_view_another_buyers_quotation(): void
    {
        $data = $this->createQuotationScenario();
        $quotation = $data['quotation'];

        $otherBuyerUser = User::factory()->create();

        BuyerProfile::create([
            'user_id' => $otherBuyerUser->id,
            'business_name' => 'Another Hardware',
            'business_type' => 'Hardware Store',
            'status' => 'active',
        ]);

        $response = $this
            ->actingAs($otherBuyerUser)
            ->getJson("/api/quotations/{$quotation->id}");

        $response
            ->assertForbidden();
    }

    public function test_user_without_supplier_profile_cannot_create_quotation(): void
    {
        $user = User::factory()->create();

        $request = BuyerRequest::create([
            'buyer_profile_id' => $this->createBuyer()->id,
            'request_number' => 'REQ-API-002',
            'title' => 'Cement requirement',
            'description' => 'Need cement.',
            'status' => 'open',
        ]);

        Sanctum::actingAs($user);

        $this->postJson(
            "/api/buyer-requests/{$request->id}/quotations"
        )
            ->assertForbidden()
            ->assertJson([
                'message' => 'You do not have a supplier profile.',
            ]);
    }

    public function test_unapproved_supplier_cannot_create_quotation(): void
    {
        $supplierUser = User::factory()->create();

        $supplier = Supplier::create([
            'user_id' => $supplierUser->id,
            'business_name' => 'Pending Supplier',
            'tin_number' => 'API-TIN-001',
            'description' => 'Pending supplier.',
            'status' => 'pending',
        ]);

        $request = BuyerRequest::create([
            'buyer_profile_id' => $this->createBuyer()->id,
            'request_number' => 'REQ-API-003',
            'title' => 'Cement requirement',
            'description' => 'Need cement.',
            'status' => 'open',
        ]);

        Sanctum::actingAs($supplierUser);

        $this->postJson(
            "/api/buyer-requests/{$request->id}/quotations"
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('supplier_id');

        $this->assertDatabaseMissing('supplier_quotations', [
            'buyer_request_id' => $request->id,
            'supplier_id' => $supplier->id,
        ]);
    }

    public function test_approved_supplier_can_create_quotation(): void
    {
        $supplierUser = User::factory()->create();

        $supplier = Supplier::create([
            'user_id' => $supplierUser->id,
            'business_name' => 'Approved Supplier',
            'tin_number' => 'API-TIN-002',
            'description' => 'Approved supplier.',
            'status' => 'approved',
        ]);

        $request = BuyerRequest::create([
            'buyer_profile_id' => $this->createBuyer()->id,
            'request_number' => 'REQ-API-004',
            'title' => 'Cement requirement',
            'description' => 'Need cement.',
            'status' => 'open',
        ]);

        Sanctum::actingAs($supplierUser);

        $response = $this->postJson(
            "/api/buyer-requests/{$request->id}/quotations"
        );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'quotation.buyer_request_id',
                $request->id
            )
            ->assertJsonPath(
                'quotation.supplier_id',
                $supplier->id
            )
            ->assertJsonPath(
                'quotation.status',
                'draft'
            );

        $this->assertDatabaseHas('supplier_quotations', [
            'buyer_request_id' => $request->id,
            'supplier_id' => $supplier->id,
            'status' => 'draft',
            'currency' => 'TZS',
        ]);
    }

    public function test_supplier_cannot_create_duplicate_quotation(): void
    {
        $supplierUser = User::factory()->create();

        $supplier = Supplier::create([
            'user_id' => $supplierUser->id,
            'business_name' => 'Approved Supplier',
            'tin_number' => 'API-TIN-003',
            'description' => 'Approved supplier.',
            'status' => 'approved',
        ]);

        $request = BuyerRequest::create([
            'buyer_profile_id' => $this->createBuyer()->id,
            'request_number' => 'REQ-API-005',
            'title' => 'Cement requirement',
            'description' => 'Need cement.',
            'status' => 'open',
        ]);

        SupplierQuotation::create([
            'buyer_request_id' => $request->id,
            'supplier_id' => $supplier->id,
            'quotation_number' => 'QUO-API-001',
            'subtotal' => 0,
            'delivery_fee' => 0,
            'total_amount' => 0,
            'currency' => 'TZS',
            'status' => 'draft',
        ]);

        Sanctum::actingAs($supplierUser);

        $this->postJson(
            "/api/buyer-requests/{$request->id}/quotations"
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('buyer_request_id');
    }

    public function test_supplier_can_add_valid_quotation_item(): void
    {
        $data = $this->createQuotationScenario();

        Sanctum::actingAs($data['supplierUser']);

        $response = $this->postJson(
            "/api/quotations/{$data['quotation']->id}/items",
            [
                'product_id' => $data['product']->id,
                'quantity' => 100,
                'unit' => 'bag',
                'unit_price' => 25000,
                'notes' => 'Factory packaging.',
            ]
        );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'item.product_id',
                $data['product']->id
            )
            ->assertJsonPath(
                'item.quantity',
                100
            );

        $this->assertDatabaseHas('supplier_quotation_items', [
            'supplier_quotation_id' => $data['quotation']->id,
            'product_id' => $data['product']->id,
            'quantity' => 100,
            'unit_price' => 25000,
            'total_price' => 2500000,
        ]);

        $this->assertDatabaseHas('supplier_quotations', [
            'id' => $data['quotation']->id,
            'subtotal' => 2500000,
            'total_amount' => 2500000,
        ]);
    }

    public function test_supplier_cannot_modify_another_suppliers_quotation(): void
    {
        $data = $this->createQuotationScenario();

        $attackerUser = User::factory()->create();

        Supplier::create([
            'user_id' => $attackerUser->id,
            'business_name' => 'Another Supplier',
            'tin_number' => 'API-TIN-004',
            'description' => 'Another supplier.',
            'status' => 'approved',
        ]);

        Sanctum::actingAs($attackerUser);

        $this->postJson(
            "/api/quotations/{$data['quotation']->id}/items",
            [
                'product_id' => $data['product']->id,
                'quantity' => 10,
                'unit' => 'bag',
                'unit_price' => 20000,
            ]
        )->assertForbidden();

        $this->assertDatabaseCount('supplier_quotation_items', 0);
    }

    public function test_supplier_cannot_add_product_not_requested_by_buyer(): void
    {
        $data = $this->createQuotationScenario();

        $otherProduct = Product::create([
            'category_id' => $data['category']->id,
            'brand_id' => $data['brand']->id,
            'name' => 'Other Product',
            'slug' => 'other-product',
            'description' => 'Other product.',
            'unit' => 'piece',
            'is_active' => true,
        ]);

        SupplierProduct::create([
            'supplier_id' => $data['supplier']->id,
            'product_id' => $otherProduct->id,
            'supplier_sku' => 'OTHER-001',
            'description' => 'Other product.',
            'is_active' => true,
        ]);

        Sanctum::actingAs($data['supplierUser']);

        $this->postJson(
            "/api/quotations/{$data['quotation']->id}/items",
            [
                'product_id' => $otherProduct->id,
                'quantity' => 10,
                'unit' => 'piece',
                'unit_price' => 10000,
            ]
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('product_id');

        $this->assertDatabaseCount('supplier_quotation_items', 0);
    }

    public function test_supplier_cannot_add_product_they_do_not_offer(): void
    {
        $data = $this->createQuotationScenario();

        $secondProduct = Product::create([
            'category_id' => $data['category']->id,
            'brand_id' => $data['brand']->id,
            'name' => 'Second Product',
            'slug' => 'second-product',
            'description' => 'Second product.',
            'unit' => 'piece',
            'is_active' => true,
        ]);

        BuyerRequestItem::create([
            'buyer_request_id' => $data['request']->id,
            'product_id' => $secondProduct->id,
            'quantity' => 10,
            'unit' => 'piece',
        ]);

        Sanctum::actingAs($data['supplierUser']);

        $this->postJson(
            "/api/quotations/{$data['quotation']->id}/items",
            [
                'product_id' => $secondProduct->id,
                'quantity' => 10,
                'unit' => 'piece',
                'unit_price' => 10000,
            ]
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('product_id');

        $this->assertDatabaseCount('supplier_quotation_items', 0);
    }

    public function test_quotation_item_requires_valid_quantity_and_price(): void
    {
        $data = $this->createQuotationScenario();

        Sanctum::actingAs($data['supplierUser']);

        $this->postJson(
            "/api/quotations/{$data['quotation']->id}/items",
            [
                'product_id' => $data['product']->id,
                'quantity' => 0,
                'unit' => 'bag',
                'unit_price' => -100,
            ]
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'quantity',
                'unit_price',
            ]);

        $this->assertDatabaseCount('supplier_quotation_items', 0);
    }

    public function test_buyer_cannot_accept_draft_quotation(): void
    {
        $data = $this->createQuotationScenario();

        Sanctum::actingAs($data['buyer']->user);

        $response = $this->postJson(
            "/api/quotations/{$data['quotation']->id}/accept"
        );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        $this->assertDatabaseHas('supplier_quotations', [
            'id' => $data['quotation']->id,
            'status' => 'draft',
        ]);
    }

    private function createBuyer(): BuyerProfile
    {
        $user = User::factory()->create();

        return BuyerProfile::create([
            'user_id' => $user->id,
            'business_name' => 'Test Buyer',
            'business_type' => 'Hardware Store',
            'status' => 'active',
        ]);
    }

    private function createQuotationScenario(): array
    {
        $supplierUser = User::factory()->create();

        $supplier = Supplier::create([
            'user_id' => $supplierUser->id,
            'business_name' => 'Test Supplier',
            'tin_number' => 'API-TIN-005',
            'description' => 'Test supplier.',
            'status' => 'approved',
        ]);

        $buyer = $this->createBuyer();

        $category = Category::create([
            'name' => 'Construction Materials',
            'slug' => 'construction-materials',
            'description' => 'Construction materials.',
            'is_active' => true,
        ]);

        $brand = Brand::create([
            'name' => 'Test Brand',
            'slug' => 'test-brand',
            'description' => 'Test brand.',
            'is_active' => true,
        ]);

        $product = Product::create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => 'Cement 50kg',
            'slug' => 'cement-50kg',
            'description' => '50kg cement bag.',
            'unit' => 'bag',
            'is_active' => true,
        ]);

        SupplierProduct::create([
            'supplier_id' => $supplier->id,
            'product_id' => $product->id,
            'supplier_sku' => 'CEM-50',
            'description' => '50kg cement.',
            'is_active' => true,
        ]);

        $request = BuyerRequest::create([
            'buyer_profile_id' => $buyer->id,
            'request_number' => 'REQ-API-SCENARIO',
            'title' => 'Cement requirement',
            'description' => 'Need cement.',
            'status' => 'open',
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
            'quotation_number' => 'QUO-API-SCENARIO',
            'subtotal' => 0,
            'delivery_fee' => 0,
            'total_amount' => 0,
            'currency' => 'TZS',
            'status' => 'draft',
        ]);

        return compact(
            'supplierUser',
            'supplier',
            'buyer',
            'category',
            'brand',
            'product',
            'request',
            'quotation'
        );
    }
}
