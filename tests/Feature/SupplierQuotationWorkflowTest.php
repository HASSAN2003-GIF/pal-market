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
use App\Models\SupplierQuotationItem;
use App\Models\User;
use App\Services\SupplierQuotationService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SupplierQuotationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_supplier_can_create_quotation_for_buyer_request(): void
    {
        // Buyer
        $buyerUser = User::factory()->create();

        $buyer = BuyerProfile::create([
            'user_id' => $buyerUser->id,
            'business_name' => 'Hassan Hardware',
            'business_type' => 'Hardware Store',
            'status' => 'active',
        ]);

        // Supplier
        $supplierUser = User::factory()->create();

        $supplier = Supplier::create([
            'user_id' => $supplierUser->id,
            'business_name' => 'Tanzania Building Supplies',
            'tin_number' => '987654321',
            'description' => 'Construction materials supplier',
            'status' => 'approved',
        ]);

        // Product category
        $category = Category::create([
            'name' => 'Construction Materials',
            'slug' => 'construction-materials',
            'description' => 'Construction materials',
            'is_active' => true,
        ]);

        // Product brand
        $brand = Brand::create([
            'name' => 'Example Cement',
            'slug' => 'example-cement',
            'description' => 'Cement brand',
            'is_active' => true,
        ]);

        // Product
        $product = Product::create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => 'Cement 50kg',
            'slug' => 'cement-50kg',
            'description' => '50kg cement bag',
            'unit' => 'bag',
            'is_active' => true,
        ]);

        // Supplier offers this product
        SupplierProduct::create([
            'supplier_id' => $supplier->id,
            'product_id' => $product->id,
            'supplier_sku' => 'CEM-50',
            'description' => '50kg cement',
            'is_active' => true,
        ]);

        // Buyer creates a request
        $request = BuyerRequest::create([
            'buyer_profile_id' => $buyer->id,
            'request_number' => 'REQ-2026-0001',
            'title' => 'Cement requirement',
            'description' => 'We need cement for a construction project.',
            'status' => 'open',
        ]);

        BuyerRequestItem::create([
            'buyer_request_id' => $request->id,
            'product_id' => $product->id,
            'quantity' => 100,
            'unit' => 'bag',
            'notes' => 'Deliver to our warehouse.',
        ]);

        // Supplier creates quotation
        $quotation = SupplierQuotation::create([
            'buyer_request_id' => $request->id,
            'supplier_id' => $supplier->id,
            'quotation_number' => 'QUO-2026-0001',
            'subtotal' => 2500000,
            'delivery_fee' => 100000,
            'total_amount' => 2600000,
            'currency' => 'TZS',
            'status' => 'submitted',
            'valid_until' => now()->addDays(7),
            'notes' => 'Prices valid for 7 days.',
        ]);

        // Supplier adds product to quotation
        $quotationItem = SupplierQuotationItem::create([
            'supplier_quotation_id' => $quotation->id,
            'product_id' => $product->id,
            'quantity' => 100,
            'unit' => 'bag',
            'unit_price' => 25000,
            'total_price' => 2500000,
            'notes' => 'Original factory packaging.',
        ]);

        // Database assertions
        $this->assertDatabaseHas('supplier_quotations', [
            'id' => $quotation->id,
            'buyer_request_id' => $request->id,
            'supplier_id' => $supplier->id,
            'quotation_number' => 'QUO-2026-0001',
            'status' => 'submitted',
        ]);

        $this->assertDatabaseHas('supplier_quotation_items', [
            'id' => $quotationItem->id,
            'supplier_quotation_id' => $quotation->id,
            'product_id' => $product->id,
            'quantity' => 100,
            'unit_price' => 25000,
            'total_price' => 2500000,
        ]);
    }

    public function test_approved_supplier_can_create_draft_quotation(): void
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
            'tin_number' => '987654323',
            'description' => 'Construction materials supplier',
            'status' => 'approved',
        ]);

        $request = BuyerRequest::create([
            'buyer_profile_id' => $buyer->id,
            'request_number' => 'REQ-CREATE-001',
            'title' => 'Cement requirement',
            'description' => 'We need cement.',
            'status' => 'open',
        ]);

        $quotation = app(SupplierQuotationService::class)->create(
            $request,
            $supplier
        );

        $this->assertDatabaseHas('supplier_quotations', [
            'id' => $quotation->id,
            'buyer_request_id' => $request->id,
            'supplier_id' => $supplier->id,
            'status' => 'draft',
            'subtotal' => 0,
            'delivery_fee' => 0,
            'total_amount' => 0,
            'currency' => 'TZS',
        ]);

        $this->assertNotEmpty($quotation->quotation_number);
    }

    public function test_unapproved_supplier_cannot_create_quotation(): void
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
            'business_name' => 'Pending Supplier',
            'tin_number' => '987654324',
            'description' => 'Supplier awaiting approval',
            'status' => 'pending',
        ]);

        $request = BuyerRequest::create([
            'buyer_profile_id' => $buyer->id,
            'request_number' => 'REQ-CREATE-002',
            'title' => 'Cement requirement',
            'description' => 'We need cement.',
            'status' => 'open',
        ]);

        $this->expectException(ValidationException::class);

        app(SupplierQuotationService::class)->create(
            $request,
            $supplier
        );

        $this->assertDatabaseMissing('supplier_quotations', [
            'buyer_request_id' => $request->id,
            'supplier_id' => $supplier->id,
        ]);
    }

    public function test_quotation_cannot_be_created_for_closed_buyer_request(): void
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
            'tin_number' => '987654325',
            'description' => 'Construction materials supplier',
            'status' => 'approved',
        ]);

        $request = BuyerRequest::create([
            'buyer_profile_id' => $buyer->id,
            'request_number' => 'REQ-CREATE-003',
            'title' => 'Cement requirement',
            'description' => 'We need cement.',
            'status' => 'closed',
        ]);

        $this->expectException(ValidationException::class);

        app(SupplierQuotationService::class)->create(
            $request,
            $supplier
        );

        $this->assertDatabaseMissing('supplier_quotations', [
            'buyer_request_id' => $request->id,
            'supplier_id' => $supplier->id,
        ]);
    }

    public function test_quotation_cannot_be_created_for_expired_buyer_request(): void
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
            'tin_number' => '987654326',
            'description' => 'Construction materials supplier',
            'status' => 'approved',
        ]);

        $request = BuyerRequest::create([
            'buyer_profile_id' => $buyer->id,
            'request_number' => 'REQ-CREATE-004',
            'title' => 'Cement requirement',
            'description' => 'We need cement.',
            'status' => 'open',
            'expires_at' => now()->subMinute(),
        ]);

        $this->expectException(ValidationException::class);

        app(SupplierQuotationService::class)->create(
            $request,
            $supplier
        );

        $this->assertDatabaseMissing('supplier_quotations', [
            'buyer_request_id' => $request->id,
            'supplier_id' => $supplier->id,
        ]);
    }

    public function test_supplier_cannot_create_two_quotations_for_same_buyer_request(): void
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
            'tin_number' => '987654327',
            'description' => 'Construction materials supplier',
            'status' => 'approved',
        ]);

        $request = BuyerRequest::create([
            'buyer_profile_id' => $buyer->id,
            'request_number' => 'REQ-CREATE-005',
            'title' => 'Cement requirement',
            'description' => 'We need cement.',
            'status' => 'open',
        ]);

        app(SupplierQuotationService::class)->create(
            $request,
            $supplier
        );

        $this->expectException(ValidationException::class);

        app(SupplierQuotationService::class)->create(
            $request,
            $supplier
        );
    }

    public function test_supplier_cannot_quote_for_product_not_in_buyer_request(): void
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
            'tin_number' => '987654321',
            'description' => 'Construction materials supplier',
            'status' => 'approved',
        ]);

        $category = Category::create([
            'name' => 'Construction Materials',
            'slug' => 'construction-materials',
            'description' => 'Construction materials',
            'is_active' => true,
        ]);

        $brand = Brand::create([
            'name' => 'Example Cement',
            'slug' => 'example-cement',
            'description' => 'Cement brand',
            'is_active' => true,
        ]);

        $requestedProduct = Product::create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => 'Cement 50kg',
            'slug' => 'cement-50kg',
            'description' => '50kg cement bag',
            'unit' => 'bag',
            'is_active' => true,
        ]);

        $unrequestedProduct = Product::create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => 'Iron Sheets',
            'slug' => 'iron-sheets',
            'description' => 'Corrugated iron sheets',
            'unit' => 'piece',
            'is_active' => true,
        ]);

        SupplierProduct::create([
            'supplier_id' => $supplier->id,
            'product_id' => $requestedProduct->id,
            'supplier_sku' => 'CEM-50',
            'description' => '50kg cement',
            'is_active' => true,
        ]);

        SupplierProduct::create([
            'supplier_id' => $supplier->id,
            'product_id' => $unrequestedProduct->id,
            'supplier_sku' => 'IRON-01',
            'description' => 'Iron sheets',
            'is_active' => true,
        ]);

        $request = BuyerRequest::create([
            'buyer_profile_id' => $buyer->id,
            'request_number' => 'REQ-2026-0002',
            'title' => 'Cement requirement',
            'description' => 'We need cement.',
            'status' => 'open',
        ]);

        BuyerRequestItem::create([
            'buyer_request_id' => $request->id,
            'product_id' => $requestedProduct->id,
            'quantity' => 100,
            'unit' => 'bag',
        ]);

        $quotation = SupplierQuotation::create([
            'buyer_request_id' => $request->id,
            'supplier_id' => $supplier->id,
            'quotation_number' => 'QUO-2026-0002',
            'subtotal' => 0,
            'delivery_fee' => 0,
            'total_amount' => 0,
            'currency' => 'TZS',
            'status' => 'draft',
        ]);

        $this->expectException(ValidationException::class);

        app(SupplierQuotationService::class)->addItem(
            $quotation,
            $unrequestedProduct->id,
            50,
            'piece',
            30000
        );

        $this->assertDatabaseMissing('supplier_quotation_items', [
            'supplier_quotation_id' => $quotation->id,
            'product_id' => $unrequestedProduct->id,
        ]);
    }

    public function test_quotation_totals_are_calculated_from_items(): void
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
            'tin_number' => '987654322',
            'description' => 'Construction materials supplier',
            'status' => 'approved',
        ]);

        $category = Category::create([
            'name' => 'Construction Materials',
            'slug' => 'construction-materials',
            'description' => 'Construction materials',
            'is_active' => true,
        ]);

        $brand = Brand::create([
            'name' => 'Example Cement',
            'slug' => 'example-cement',
            'description' => 'Cement brand',
            'is_active' => true,
        ]);

        $product = Product::create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => 'Cement 50kg',
            'slug' => 'cement-50kg',
            'description' => '50kg cement bag',
            'unit' => 'bag',
            'is_active' => true,
        ]);

        SupplierProduct::create([
            'supplier_id' => $supplier->id,
            'product_id' => $product->id,
            'supplier_sku' => 'CEM-50',
            'description' => '50kg cement',
            'is_active' => true,
        ]);

        $request = BuyerRequest::create([
            'buyer_profile_id' => $buyer->id,
            'request_number' => 'REQ-2026-0003',
            'title' => 'Cement requirement',
            'description' => 'We need cement.',
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
            'quotation_number' => 'QUO-2026-0003',
            'subtotal' => 0,
            'delivery_fee' => 100000,
            'total_amount' => 0,
            'currency' => 'TZS',
            'status' => 'draft',
        ]);

        $service = app(SupplierQuotationService::class);

        $service->addItem(
            $quotation,
            $product->id,
            100,
            'bag',
            25000
        );

        $quotation->refresh();

        $this->assertEquals(2500000, $quotation->subtotal);
        $this->assertEquals(2600000, $quotation->total_amount);
    }

    public function test_supplier_cannot_quote_for_product_it_does_not_offer(): void
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
            'tin_number' => '987654323',
            'description' => 'Construction materials supplier',
            'status' => 'approved',
        ]);

        $category = Category::create([
            'name' => 'Construction Materials',
            'slug' => 'construction-materials',
            'description' => 'Construction materials',
            'is_active' => true,
        ]);

        $brand = Brand::create([
            'name' => 'Example Cement',
            'slug' => 'example-cement',
            'description' => 'Cement brand',
            'is_active' => true,
        ]);

        $product = Product::create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => 'Cement 50kg',
            'slug' => 'cement-50kg',
            'description' => '50kg cement bag',
            'unit' => 'bag',
            'is_active' => true,
        ]);

        // Notice: supplier does NOT offer this product.

        $request = BuyerRequest::create([
            'buyer_profile_id' => $buyer->id,
            'request_number' => 'REQ-2026-0004',
            'title' => 'Cement requirement',
            'description' => 'We need cement.',
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
            'quotation_number' => 'QUO-2026-0004',
            'subtotal' => 0,
            'delivery_fee' => 0,
            'total_amount' => 0,
            'currency' => 'TZS',
            'status' => 'draft',
        ]);

        $this->expectException(ValidationException::class);

        app(SupplierQuotationService::class)->addItem(
            $quotation,
            $product->id,
            100,
            'bag',
            25000
        );

        $this->assertDatabaseMissing('supplier_quotation_items', [
            'supplier_quotation_id' => $quotation->id,
            'product_id' => $product->id,
        ]);
    }

    public function test_supplier_cannot_add_same_product_twice_to_quotation(): void
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
            'tin_number' => '987654324',
            'description' => 'Construction materials supplier',
            'status' => 'approved',
        ]);

        $category = Category::create([
            'name' => 'Construction Materials',
            'slug' => 'construction-materials',
            'description' => 'Construction materials',
            'is_active' => true,
        ]);

        $brand = Brand::create([
            'name' => 'Example Cement',
            'slug' => 'example-cement',
            'description' => 'Cement brand',
            'is_active' => true,
        ]);

        $product = Product::create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => 'Cement 50kg',
            'slug' => 'cement-50kg',
            'description' => '50kg cement bag',
            'unit' => 'bag',
            'is_active' => true,
        ]);

        SupplierProduct::create([
            'supplier_id' => $supplier->id,
            'product_id' => $product->id,
            'supplier_sku' => 'CEM-50',
            'description' => '50kg cement',
            'is_active' => true,
        ]);

        $request = BuyerRequest::create([
            'buyer_profile_id' => $buyer->id,
            'request_number' => 'REQ-2026-0005',
            'title' => 'Cement requirement',
            'description' => 'We need cement.',
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
            'quotation_number' => 'QUO-2026-0005',
            'subtotal' => 0,
            'delivery_fee' => 0,
            'total_amount' => 0,
            'currency' => 'TZS',
            'status' => 'draft',
        ]);

        $service = app(SupplierQuotationService::class);

        $service->addItem(
            $quotation,
            $product->id,
            100,
            'bag',
            25000
        );

        $this->expectException(QueryException::class);

        $service->addItem(
            $quotation,
            $product->id,
            50,
            'bag',
            24000
        );
    }

    public function test_non_draft_quotation_cannot_add_item(): void
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
            'tin_number' => '987654323',
            'description' => 'Construction materials supplier',
            'status' => 'approved',
        ]);

        $category = Category::create([
            'name' => 'Construction Materials',
            'slug' => 'construction-materials',
            'description' => 'Construction materials',
            'is_active' => true,
        ]);

        $brand = Brand::create([
            'name' => 'Example Cement',
            'slug' => 'example-cement',
            'description' => 'Cement brand',
            'is_active' => true,
        ]);

        $product = Product::create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => 'Cement 50kg',
            'slug' => 'cement-50kg',
            'description' => '50kg cement bag',
            'unit' => 'bag',
            'is_active' => true,
        ]);

        SupplierProduct::create([
            'supplier_id' => $supplier->id,
            'product_id' => $product->id,
            'supplier_sku' => 'CEM-50',
            'description' => '50kg cement',
            'is_active' => true,
        ]);

        $request = BuyerRequest::create([
            'buyer_profile_id' => $buyer->id,
            'request_number' => 'REQ-2026-0004',
            'title' => 'Cement requirement',
            'description' => 'We need cement.',
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
            'quotation_number' => 'QUO-2026-0004',
            'subtotal' => 0,
            'delivery_fee' => 0,
            'total_amount' => 0,
            'currency' => 'TZS',
            'status' => 'submitted',
        ]);

        $this->expectException(ValidationException::class);

        app(SupplierQuotationService::class)->addItem(
            $quotation,
            $product->id,
            50,
            'bag',
            25000
        );

        $this->assertDatabaseMissing('supplier_quotation_items', [
            'supplier_quotation_id' => $quotation->id,
            'product_id' => $product->id,
        ]);
    }

    public function test_quotation_item_quantity_must_be_greater_than_zero(): void
    {
        $quotation = $this->createQuotationForWorkflowTest();

        $this->expectException(ValidationException::class);

        app(SupplierQuotationService::class)->addItem(
            $quotation,
            $quotation->buyerRequest->items()->first()->product_id,
            0,
            'bag',
            25000
        );
    }

    public function test_quotation_item_unit_price_cannot_be_negative(): void
    {
        $quotation = $this->createQuotationForWorkflowTest();

        $this->expectException(ValidationException::class);

        app(SupplierQuotationService::class)->addItem(
            $quotation,
            $quotation->buyerRequest->items()->first()->product_id,
            10,
            'bag',
            -25000
        );
    }

    private function createQuotationForWorkflowTest(): SupplierQuotation
    {
        $buyerUser = User::factory()->create();

        $buyer = BuyerProfile::create([
            'user_id' => $buyerUser->id,
            'business_name' => 'Test Buyer',
            'business_type' => 'Hardware Store',
            'status' => 'active',
        ]);

        $supplierUser = User::factory()->create();

        $supplier = Supplier::create([
            'user_id' => $supplierUser->id,
            'business_name' => 'Test Supplier',
            'tin_number' => 'TIN-'.uniqid(),
            'description' => 'Test supplier',
            'status' => 'approved',
        ]);

        $category = Category::create([
            'name' => 'Test Category',
            'slug' => 'test-category-'.uniqid(),
            'description' => 'Test category',
            'is_active' => true,
        ]);

        $brand = Brand::create([
            'name' => 'Test Brand',
            'slug' => 'test-brand-'.uniqid(),
            'description' => 'Test brand',
            'is_active' => true,
        ]);

        $product = Product::create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => 'Test Product',
            'slug' => 'test-product-'.uniqid(),
            'description' => 'Test product',
            'unit' => 'bag',
            'is_active' => true,
        ]);

        SupplierProduct::create([
            'supplier_id' => $supplier->id,
            'product_id' => $product->id,
            'supplier_sku' => 'TEST-'.uniqid(),
            'description' => 'Test supplier product',
            'is_active' => true,
        ]);

        $request = BuyerRequest::create([
            'buyer_profile_id' => $buyer->id,
            'request_number' => 'REQ-'.uniqid(),
            'title' => 'Test Request',
            'description' => 'Test buyer request',
            'status' => 'open',
        ]);

        BuyerRequestItem::create([
            'buyer_request_id' => $request->id,
            'product_id' => $product->id,
            'quantity' => 100,
            'unit' => 'bag',
        ]);

        return SupplierQuotation::create([
            'buyer_request_id' => $request->id,
            'supplier_id' => $supplier->id,
            'quotation_number' => 'QUO-'.uniqid(),
            'subtotal' => 0,
            'delivery_fee' => 0,
            'total_amount' => 0,
            'currency' => 'TZS',
            'status' => 'draft',
        ]);
    }
}
