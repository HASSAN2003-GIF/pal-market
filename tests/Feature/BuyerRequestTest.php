<?php

namespace Tests\Feature;

use App\Models\BuyerProfile;
use App\Models\BuyerRequest;
use App\Models\BuyerRequestItem;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Services\BuyerRequestStatusService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BuyerRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_buyer_can_create_a_request_with_products(): void
    {
        $user = User::factory()->create();

        $buyer = BuyerProfile::create([
            'user_id' => $user->id,
            'business_name' => 'Hassan Construction',
            'business_type' => 'Hardware Store',
            'tin_number' => '111222333',
            'description' => 'Construction materials buyer',
            'status' => 'active',
        ]);

        $category = Category::create([
            'name' => 'Construction Materials',
            'slug' => 'construction-materials',
            'description' => 'Construction materials',
            'is_active' => true,
        ]);

        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Cement 50kg',
            'slug' => 'cement-50kg',
            'description' => '50kg cement bag',
            'unit' => 'bag',
            'is_active' => true,
        ]);

        $request = BuyerRequest::create([
            'buyer_profile_id' => $buyer->id,
            'request_number' => 'REQ-000001',
            'title' => 'Cement requirement',
            'description' => 'We need cement for a construction project.',
            'status' => 'open',
        ]);

        $item = BuyerRequestItem::create([
            'buyer_request_id' => $request->id,
            'product_id' => $product->id,
            'quantity' => 100,
            'unit' => 'bag',
            'notes' => 'Deliver to our construction site.',
        ]);

        $this->assertDatabaseHas('buyer_requests', [
            'id' => $request->id,
            'buyer_profile_id' => $buyer->id,
            'request_number' => 'REQ-000001',
            'status' => 'open',
        ]);

        $this->assertDatabaseHas('buyer_request_items', [
            'id' => $item->id,
            'buyer_request_id' => $request->id,
            'product_id' => $product->id,
            'quantity' => 100,
        ]);
    }
    public function test_buyer_cannot_add_the_same_product_twice_to_a_request(): void
{
    $user = User::factory()->create();

    $buyer = BuyerProfile::create([
        'user_id' => $user->id,
        'business_name' => 'Hassan Hardware',
        'business_type' => 'Hardware Store',
        'tin_number' => '444555666',
        'description' => 'Construction materials buyer',
        'status' => 'active',
    ]);

    $category = Category::create([
        'name' => 'Plumbing',
        'slug' => 'plumbing',
        'description' => 'Plumbing materials',
        'is_active' => true,
    ]);

    $product = Product::create([
        'category_id' => $category->id,
        'name' => 'PVC Pipe 1 Inch',
        'slug' => 'pvc-pipe-1-inch',
        'description' => '1 inch PVC pipe',
        'unit' => 'piece',
        'is_active' => true,
    ]);

    $request = BuyerRequest::create([
        'buyer_profile_id' => $buyer->id,
        'request_number' => 'REQ-000002',
        'title' => 'Plumbing materials',
        'description' => 'Required plumbing materials.',
        'status' => 'open',
    ]);

    BuyerRequestItem::create([
        'buyer_request_id' => $request->id,
        'product_id' => $product->id,
        'quantity' => 50,
        'unit' => 'piece',
    ]);

    $this->expectException(\Illuminate\Database\QueryException::class);

    BuyerRequestItem::create([
        'buyer_request_id' => $request->id,
        'product_id' => $product->id,
        'quantity' => 20,
        'unit' => 'piece',
    ]);
}
public function test_buyer_request_relationships_work(): void
{
    $user = User::factory()->create();

    $buyer = BuyerProfile::create([
        'user_id' => $user->id,
        'business_name' => 'Relationship Test Buyer',
        'business_type' => 'Hardware Store',
        'tin_number' => '777888999',
        'description' => 'Testing relationships',
        'status' => 'active',
    ]);

    $category = Category::create([
        'name' => 'Roofing',
        'slug' => 'roofing',
        'description' => 'Roofing materials',
        'is_active' => true,
    ]);

    $product = Product::create([
        'category_id' => $category->id,
        'name' => 'Iron Sheet',
        'slug' => 'iron-sheet',
        'description' => 'Roofing iron sheet',
        'unit' => 'piece',
        'is_active' => true,
    ]);

    $request = BuyerRequest::create([
        'buyer_profile_id' => $buyer->id,
        'request_number' => 'REQ-000003',
        'title' => 'Roofing materials',
        'description' => 'Need roofing materials.',
        'status' => 'open',
    ]);

    $item = BuyerRequestItem::create([
        'buyer_request_id' => $request->id,
        'product_id' => $product->id,
        'quantity' => 100,
        'unit' => 'piece',
    ]);

    $this->assertTrue(
        $buyer->requests->contains($request)
    );

    $this->assertTrue(
        $request->items->contains($item)
    );

    $this->assertTrue(
        $item->product->is($product)
    );
}
public function test_draft_buyer_request_can_be_published(): void
{
    $user = User::factory()->create();

    $buyer = BuyerProfile::create([
        'user_id' => $user->id,
        'business_name' => 'Hassan Hardware',
        'business_type' => 'Hardware Store',
        'tin_number' => '123456789',
        'description' => 'Hardware business',
        'status' => 'active',
    ]);

    $request = BuyerRequest::create([
        'buyer_profile_id' => $buyer->id,
        'request_number' => 'REQ-PUBLISH-001',
        'title' => 'Cement requirement',
        'description' => 'Need cement.',
        'status' => 'draft',
    ]);

    $request = app(\App\Services\BuyerRequestStatusService::class)
        ->publish($request);

    $this->assertEquals('open', $request->status);
}
public function test_open_buyer_request_cannot_be_published_again(): void
{
    $user = User::factory()->create();

    $buyer = BuyerProfile::create([
        'user_id' => $user->id,
        'business_name' => 'Hassan Hardware',
        'business_type' => 'Hardware Store',
        'tin_number' => '123456789',
        'description' => 'Hardware business',
        'status' => 'active',
    ]);

    $request = BuyerRequest::create([
        'buyer_profile_id' => $buyer->id,
        'request_number' => 'REQ-PUBLISH-002',
        'title' => 'Cement requirement',
        'description' => 'Need cement.',
        'status' => 'open',
    ]);

    $this->expectException(\Illuminate\Validation\ValidationException::class);

    app(\App\Services\BuyerRequestStatusService::class)
        ->publish($request);
}
public function test_draft_buyer_request_can_be_cancelled(): void
{
    $request = $this->createBuyerRequest('draft');

    $request = app(\App\Services\BuyerRequestStatusService::class)
        ->cancel($request);

    $this->assertEquals('cancelled', $request->status);
}

public function test_open_buyer_request_can_be_cancelled(): void
{
    $request = $this->createBuyerRequest('open');

    $request = app(\App\Services\BuyerRequestStatusService::class)
        ->cancel($request);

    $this->assertEquals('cancelled', $request->status);
}

public function test_closed_buyer_request_cannot_be_cancelled(): void
{
    $request = $this->createBuyerRequest('closed');

    $this->expectException(\Illuminate\Validation\ValidationException::class);

    app(\App\Services\BuyerRequestStatusService::class)
        ->cancel($request);
}

public function test_cancelled_buyer_request_cannot_be_cancelled_again(): void
{
    $request = $this->createBuyerRequest('cancelled');

    $this->expectException(\Illuminate\Validation\ValidationException::class);

    app(\App\Services\BuyerRequestStatusService::class)
        ->cancel($request);
}
public function test_open_buyer_request_can_expire_when_expiration_time_has_passed(): void
{
    $request = $this->createBuyerRequest('open');

    $request->update([
        'expires_at' => now()->subMinute(),
    ]);

    $request = app(BuyerRequestStatusService::class)
        ->expire($request);

    $this->assertEquals('expired', $request->status);
}

public function test_open_buyer_request_cannot_expire_before_expiration_time(): void
{
    $request = $this->createBuyerRequest('open');

    $request->update([
        'expires_at' => now()->addMinute(),
    ]);

    $this->expectException(ValidationException::class);

    app(BuyerRequestStatusService::class)
        ->expire($request);
}

public function test_draft_buyer_request_cannot_expire(): void
{
    $request = $this->createBuyerRequest('draft');

    $request->update([
        'expires_at' => now()->subMinute(),
    ]);

    $this->expectException(ValidationException::class);

    app(BuyerRequestStatusService::class)
        ->expire($request);
}

public function test_expired_buyer_request_cannot_expire_again(): void
{
    $request = $this->createBuyerRequest('expired');

    $request->update([
        'expires_at' => now()->subMinute(),
    ]);

    $this->expectException(ValidationException::class);

    app(BuyerRequestStatusService::class)
        ->expire($request);
}
private function createBuyerRequest(string $status): BuyerRequest
{
    $user = User::factory()->create();

    $buyer = BuyerProfile::create([
        'user_id' => $user->id,
        'business_name' => 'Test Hardware',
        'business_type' => 'Hardware Store',
        'tin_number' => '999888777',
        'description' => 'Test buyer',
        'status' => 'active',
    ]);

    return BuyerRequest::create([
        'buyer_profile_id' => $buyer->id,
        'request_number' => 'REQ-' . uniqid(),
        'title' => 'Test request',
        'description' => 'Test buyer request.',
        'status' => $status,
    ]);
}
}