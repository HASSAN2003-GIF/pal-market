<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\SupplierQuotation;
use App\Models\SupplierQuotationItem;
use App\Models\User;
use App\Models\BuyerProfile;
use App\Models\BuyerRequest;
use App\Models\BuyerRequestItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Services\SupplierQuotationService;
use Illuminate\Validation\ValidationException;

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

    $service = app(\App\Services\SupplierQuotationService::class);

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

    $this->expectException(\Illuminate\Database\QueryException::class);

    $service->addItem(
        $quotation,
        $product->id,
        50,
        'bag',
        24000
    );
}
}