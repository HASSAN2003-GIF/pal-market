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
use App\Services\SupplierQuotationStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SupplierQuotationStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_draft_quotation_can_be_submitted(): void
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
            'tin_number' => '999999999',
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
            'request_number' => 'REQ-2026-STATUS-001',
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
            'quotation_number' => 'QUO-2026-STATUS-001',
            'subtotal' => 2500000,
            'delivery_fee' => 100000,
            'total_amount' => 2600000,
            'currency' => 'TZS',
            'status' => 'draft',
        ]);

        $quotation = app(SupplierQuotationStatusService::class)
            ->submit($quotation);

        $this->assertEquals('submitted', $quotation->status);
    }

    private function createQuotation(
        string $status = 'draft'
    ): SupplierQuotation {
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
            'tin_number' => '999999999',
            'description' => 'Construction materials supplier',
            'status' => 'approved',
        ]);

        $request = BuyerRequest::create([
            'buyer_profile_id' => $buyer->id,
            'request_number' => 'REQ-'.uniqid(),
            'title' => 'Cement requirement',
            'description' => 'We need cement.',
            'status' => 'open',
        ]);

        return SupplierQuotation::create([
            'buyer_request_id' => $request->id,
            'supplier_id' => $supplier->id,
            'quotation_number' => 'QUO-'.uniqid(),
            'subtotal' => 1000000,
            'delivery_fee' => 50000,
            'total_amount' => 1050000,
            'currency' => 'TZS',
            'status' => $status,
        ]);
    }

    public function test_submitted_quotation_cannot_be_submitted_again(): void
    {
        $quotation = $this->createQuotation('submitted');

        $this->expectException(
            ValidationException::class
        );

        app(SupplierQuotationStatusService::class)
            ->submit($quotation);
    }

    public function test_submitted_quotation_can_be_accepted(): void
    {
        $quotation = $this->createQuotation('submitted');

        $quotation = app(SupplierQuotationStatusService::class)
            ->accept($quotation);

        $this->assertEquals('accepted', $quotation->status);
    }

    public function test_draft_quotation_cannot_be_accepted(): void
    {
        $quotation = $this->createQuotation('draft');

        $this->expectException(
            ValidationException::class
        );

        app(SupplierQuotationStatusService::class)
            ->accept($quotation);
    }

    public function test_cannot_accept_second_quotation_for_same_buyer_request(): void
    {
        $buyerUser = User::factory()->create();

        $buyer = BuyerProfile::create([
            'user_id' => $buyerUser->id,
            'business_name' => 'Hassan Hardware',
            'business_type' => 'Hardware Store',
            'status' => 'active',
        ]);

        $supplierUser1 = User::factory()->create();

        $supplier1 = Supplier::create([
            'user_id' => $supplierUser1->id,
            'business_name' => 'Supplier One',
            'tin_number' => '111111111',
            'description' => 'First supplier',
            'status' => 'approved',
        ]);

        $supplierUser2 = User::factory()->create();

        $supplier2 = Supplier::create([
            'user_id' => $supplierUser2->id,
            'business_name' => 'Supplier Two',
            'tin_number' => '222222222',
            'description' => 'Second supplier',
            'status' => 'approved',
        ]);

        $request = BuyerRequest::create([
            'buyer_profile_id' => $buyer->id,
            'request_number' => 'REQ-'.uniqid(),
            'title' => 'Cement requirement',
            'description' => 'We need cement.',
            'status' => 'open',
        ]);

        $firstQuotation = SupplierQuotation::create([
            'buyer_request_id' => $request->id,
            'supplier_id' => $supplier1->id,
            'quotation_number' => 'QUO-'.uniqid(),
            'subtotal' => 1000000,
            'delivery_fee' => 50000,
            'total_amount' => 1050000,
            'currency' => 'TZS',
            'status' => 'submitted',
        ]);

        $secondQuotation = SupplierQuotation::create([
            'buyer_request_id' => $request->id,
            'supplier_id' => $supplier2->id,
            'quotation_number' => 'QUO-'.uniqid(),
            'subtotal' => 1200000,
            'delivery_fee' => 50000,
            'total_amount' => 1250000,
            'currency' => 'TZS',
            'status' => 'submitted',
        ]);

        $service = app(SupplierQuotationStatusService::class);

        $acceptedQuotation = $service->accept($firstQuotation);

        $this->assertEquals('accepted', $acceptedQuotation->status);

        $this->expectException(
            ValidationException::class
        );

        $service->accept($secondQuotation);
    }

    public function test_accepting_quotation_rejects_other_submitted_quotations(): void
    {
        $buyerUser = User::factory()->create();

        $buyer = BuyerProfile::create([
            'user_id' => $buyerUser->id,
            'business_name' => 'Hassan Hardware',
            'business_type' => 'Hardware Store',
            'status' => 'active',
        ]);

        $supplierUser1 = User::factory()->create();

        $supplier1 = Supplier::create([
            'user_id' => $supplierUser1->id,
            'business_name' => 'Supplier One',
            'tin_number' => '111111111',
            'description' => 'First supplier',
            'status' => 'approved',
        ]);

        $supplierUser2 = User::factory()->create();

        $supplier2 = Supplier::create([
            'user_id' => $supplierUser2->id,
            'business_name' => 'Supplier Two',
            'tin_number' => '222222222',
            'description' => 'Second supplier',
            'status' => 'approved',
        ]);

        $request = BuyerRequest::create([
            'buyer_profile_id' => $buyer->id,
            'request_number' => 'REQ-'.uniqid(),
            'title' => 'Cement requirement',
            'description' => 'We need cement.',
            'status' => 'open',
        ]);

        $quotationOne = SupplierQuotation::create([
            'buyer_request_id' => $request->id,
            'supplier_id' => $supplier1->id,
            'quotation_number' => 'QUO-'.uniqid(),
            'subtotal' => 1000000,
            'delivery_fee' => 50000,
            'total_amount' => 1050000,
            'currency' => 'TZS',
            'status' => 'submitted',
        ]);

        $quotationTwo = SupplierQuotation::create([
            'buyer_request_id' => $request->id,
            'supplier_id' => $supplier2->id,
            'quotation_number' => 'QUO-'.uniqid(),
            'subtotal' => 1200000,
            'delivery_fee' => 50000,
            'total_amount' => 1250000,
            'currency' => 'TZS',
            'status' => 'submitted',
        ]);

        $service = app(SupplierQuotationStatusService::class);

        $service->accept($quotationOne);

        $quotationOne->refresh();
        $quotationTwo->refresh();

        $this->assertEquals('accepted', $quotationOne->status);
        $this->assertEquals('rejected', $quotationTwo->status);
    }

    public function test_accepting_quotation_closes_buyer_request(): void
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
            'tin_number' => '333333333',
            'description' => 'Construction materials supplier',
            'status' => 'approved',
        ]);

        $request = BuyerRequest::create([
            'buyer_profile_id' => $buyer->id,
            'request_number' => 'REQ-'.uniqid(),
            'title' => 'Cement requirement',
            'description' => 'We need cement.',
            'status' => 'open',
        ]);

        $quotation = SupplierQuotation::create([
            'buyer_request_id' => $request->id,
            'supplier_id' => $supplier->id,
            'quotation_number' => 'QUO-'.uniqid(),
            'subtotal' => 1000000,
            'delivery_fee' => 50000,
            'total_amount' => 1050000,
            'currency' => 'TZS',
            'status' => 'submitted',
        ]);

        app(SupplierQuotationStatusService::class)
            ->accept($quotation);

        $request->refresh();

        $this->assertEquals('closed', $request->status);
    }

    public function test_quotation_cannot_be_submitted_when_buyer_request_is_closed(): void
    {
        $quotation = $this->createQuotation('draft');

        $quotation->buyerRequest->update([
            'status' => 'closed',
        ]);

        $this->expectException(ValidationException::class);

        app(SupplierQuotationStatusService::class)
            ->submit($quotation);
    }

    public function test_quotation_cannot_be_submitted_when_buyer_request_has_expired(): void
    {
        $quotation = $this->createQuotation('draft');

        $quotation->buyerRequest->update([
            'status' => 'open',
            'expires_at' => now()->subMinute(),
        ]);

        $this->expectException(ValidationException::class);

        app(SupplierQuotationStatusService::class)
            ->submit($quotation);
    }
}
