<?php

namespace Tests\Feature;

use App\Models\BuyerRequest;
use App\Models\BuyerRequestItem;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierQuotationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_approved_supplier_can_create_a_quotation(): void
    {
        $user = User::factory()->create();

        $supplier = Supplier::create([
            'user_id' => $user->id,
            'business_name' => 'Test Hardware',
            'status' => 'approved',
        ]);

        $buyerUser = User::factory()->create();

        $buyerProfile = $buyerUser->buyerProfile()->create([
            'company_name' => 'Test Buyer',
        ]);

        $buyerRequest = BuyerRequest::create([
            'buyer_profile_id' => $buyerProfile->id,
            'request_number' => 'REQ-001',
            'title' => 'Cement Request',
            'description' => 'Need cement',
            'status' => 'open',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/supplier/quotations', [
                'buyer_request_id' => $buyerRequest->id,
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.supplier_id', $supplier->id)
            ->assertJsonPath('data.buyer_request_id', $buyerRequest->id);

        $this->assertDatabaseHas('supplier_quotations', [
            'buyer_request_id' => $buyerRequest->id,
            'supplier_id' => $supplier->id,
            'status' => 'draft',
        ]);
    }

    public function test_unauthenticated_user_cannot_create_a_quotation(): void
    {
        $response = $this->postJson('/api/supplier/quotations', [
            'buyer_request_id' => 1,
        ]);

        $response->assertUnauthorized();
    }

    public function test_unapproved_supplier_cannot_create_a_quotation(): void
    {
        $user = User::factory()->create();

        $supplier = Supplier::create([
            'user_id' => $user->id,
            'business_name' => 'Pending Hardware',
            'status' => 'pending',
        ]);

        $buyerUser = User::factory()->create();

        $buyerProfile = $buyerUser->buyerProfile()->create([
            'company_name' => 'Test Buyer',
        ]);

        $buyerRequest = BuyerRequest::create([
            'buyer_profile_id' => $buyerProfile->id,
            'request_number' => 'REQ-002',
            'title' => 'Cement Request',
            'description' => 'Need cement',
            'status' => 'open',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/supplier/quotations', [
                'buyer_request_id' => $buyerRequest->id,
            ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors('supplier_id');
    }

    public function test_supplier_cannot_create_quotation_for_another_supplier(): void
    {
        $user = User::factory()->create();

        Supplier::create([
            'user_id' => $user->id,
            'business_name' => 'My Hardware',
            'status' => 'approved',
        ]);

        $otherSupplierUser = User::factory()->create();

        $otherSupplier = Supplier::create([
            'user_id' => $otherSupplierUser->id,
            'business_name' => 'Other Hardware',
            'status' => 'approved',
        ]);

        $buyerUser = User::factory()->create();

        $buyerProfile = $buyerUser->buyerProfile()->create([
            'company_name' => 'Test Buyer',
        ]);

        $buyerRequest = BuyerRequest::create([
            'buyer_profile_id' => $buyerProfile->id,
            'request_number' => 'REQ-003',
            'title' => 'Cement Request',
            'description' => 'Need cement',
            'status' => 'open',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/supplier/quotations', [
                'buyer_request_id' => $buyerRequest->id,
                'supplier_id' => $otherSupplier->id,
            ]);

        $response
            ->assertStatus(422);

        $this->assertDatabaseMissing('supplier_quotations', [
            'buyer_request_id' => $buyerRequest->id,
            'supplier_id' => $otherSupplier->id,
        ]);
    }
}