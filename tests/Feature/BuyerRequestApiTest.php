<?php

namespace Tests\Feature;

use App\Models\BuyerProfile;
use App\Models\BuyerRequest;
use App\Models\BuyerRequestItem;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BuyerRequestApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_access_buyer_requests(): void
    {
        $response = $this->getJson('/api/buyer-requests');

        $response->assertUnauthorized();
    }

    public function test_user_without_buyer_profile_cannot_access_buyer_requests(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/buyer-requests');

        $response->assertForbidden();
    }

    public function test_buyer_can_list_their_own_requests(): void
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

        $ownRequest = BuyerRequest::create([
            'buyer_profile_id' => $buyer->id,
            'request_number' => 'REQ-API-001',
            'title' => 'Cement requirement',
            'description' => 'Need cement.',
            'status' => 'draft',
        ]);

        $otherUser = User::factory()->create();

        $otherBuyer = BuyerProfile::create([
            'user_id' => $otherUser->id,
            'business_name' => 'Other Hardware',
            'business_type' => 'Hardware Store',
            'tin_number' => '987654321',
            'description' => 'Other business',
            'status' => 'active',
        ]);

        BuyerRequest::create([
            'buyer_profile_id' => $otherBuyer->id,
            'request_number' => 'REQ-API-002',
            'title' => 'Other request',
            'description' => 'Other request.',
            'status' => 'open',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/buyer-requests');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'buyer_requests')
            ->assertJsonPath(
                'buyer_requests.0.id',
                $ownRequest->id
            );
    }

    public function test_buyer_can_create_a_draft_request(): void
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

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/buyer-requests', [
            'title' => 'Cement requirement',
            'description' => 'Need 100 bags of cement.',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath(
                'buyer_request.buyer_profile_id',
                $buyer->id
            )
            ->assertJsonPath(
                'buyer_request.title',
                'Cement requirement'
            )
            ->assertJsonPath(
                'buyer_request.status',
                'draft'
            );

        $this->assertDatabaseHas('buyer_requests', [
            'buyer_profile_id' => $buyer->id,
            'title' => 'Cement requirement',
            'status' => 'draft',
        ]);
    }

    public function test_buyer_request_creation_requires_a_title(): void
    {
        $user = User::factory()->create();

        BuyerProfile::create([
            'user_id' => $user->id,
            'business_name' => 'Validation Hardware',
            'business_type' => 'Hardware Store',
            'tin_number' => '111222333',
            'description' => 'Validation business',
            'status' => 'active',
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/buyer-requests', [
            'description' => 'Missing title.',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors('title');
    }

    public function test_buyer_can_view_their_own_request(): void
    {
        $user = User::factory()->create();

        $buyer = BuyerProfile::create([
            'user_id' => $user->id,
            'business_name' => 'View Hardware',
            'business_type' => 'Hardware Store',
            'tin_number' => '444555666',
            'description' => 'View business',
            'status' => 'active',
        ]);

        $request = BuyerRequest::create([
            'buyer_profile_id' => $buyer->id,
            'request_number' => 'REQ-API-003',
            'title' => 'View request',
            'description' => 'View my request.',
            'status' => 'draft',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson(
            "/api/buyer-requests/{$request->id}"
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'buyer_request.id',
                $request->id
            );
    }

    public function test_buyer_cannot_view_another_buyers_request(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        $buyer = BuyerProfile::create([
            'user_id' => $owner->id,
            'business_name' => 'Owner Hardware',
            'business_type' => 'Hardware Store',
            'tin_number' => '777888999',
            'description' => 'Owner business',
            'status' => 'active',
        ]);

        $request = BuyerRequest::create([
            'buyer_profile_id' => $buyer->id,
            'request_number' => 'REQ-API-004',
            'title' => 'Private request',
            'description' => 'Private request.',
            'status' => 'draft',
        ]);

        Sanctum::actingAs($otherUser);

        $response = $this->getJson(
            "/api/buyer-requests/{$request->id}"
        );

        $response->assertForbidden();
    }

    public function test_buyer_can_update_their_draft_request(): void
    {
        $user = User::factory()->create();

        $buyer = BuyerProfile::create([
            'user_id' => $user->id,
            'business_name' => 'Update Hardware',
            'business_type' => 'Hardware Store',
            'tin_number' => '222333444',
            'description' => 'Update business',
            'status' => 'active',
        ]);

        $request = BuyerRequest::create([
            'buyer_profile_id' => $buyer->id,
            'request_number' => 'REQ-API-005',
            'title' => 'Old title',
            'description' => 'Old description.',
            'status' => 'draft',
        ]);

        Sanctum::actingAs($user);

        $response = $this->putJson(
            "/api/buyer-requests/{$request->id}",
            [
                'title' => 'Updated title',
                'description' => 'Updated description.',
            ]
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'buyer_request.title',
                'Updated title'
            );

        $this->assertDatabaseHas('buyer_requests', [
            'id' => $request->id,
            'title' => 'Updated title',
            'description' => 'Updated description.',
            'status' => 'draft',
        ]);
    }

    public function test_buyer_cannot_update_an_open_request(): void
    {
        $user = User::factory()->create();

        $buyer = BuyerProfile::create([
            'user_id' => $user->id,
            'business_name' => 'Open Hardware',
            'business_type' => 'Hardware Store',
            'tin_number' => '555666777',
            'description' => 'Open business',
            'status' => 'active',
        ]);

        $request = BuyerRequest::create([
            'buyer_profile_id' => $buyer->id,
            'request_number' => 'REQ-API-006',
            'title' => 'Open request',
            'description' => 'Open request.',
            'status' => 'open',
        ]);

        Sanctum::actingAs($user);

        $response = $this->putJson(
            "/api/buyer-requests/{$request->id}",
            [
                'title' => 'Should not update',
            ]
        );

        $response->assertForbidden();
    }

    public function test_buyer_can_add_product_to_draft_request(): void
    {
        $user = User::factory()->create();

        $buyer = BuyerProfile::create([
            'user_id' => $user->id,
            'business_name' => 'Product Hardware',
            'business_type' => 'Hardware Store',
            'tin_number' => '888999000',
            'description' => 'Product business',
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
            'request_number' => 'REQ-API-007',
            'title' => 'Construction materials',
            'description' => 'Materials required.',
            'status' => 'draft',
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson(
            "/api/buyer-requests/{$request->id}/items",
            [
                'product_id' => $product->id,
                'quantity' => 100,
                'unit' => 'bag',
                'notes' => 'Deliver to site.',
            ]
        );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'item.product_id',
                $product->id
            )
            ->assertJsonPath(
                'item.quantity',
                100
            );

        $this->assertDatabaseHas('buyer_request_items', [
            'buyer_request_id' => $request->id,
            'product_id' => $product->id,
            'quantity' => 100,
            'unit' => 'bag',
        ]);
    }

    public function test_buyer_cannot_add_the_same_product_twice(): void
    {
        $user = User::factory()->create();

        $buyer = BuyerProfile::create([
            'user_id' => $user->id,
            'business_name' => 'Duplicate Hardware',
            'business_type' => 'Hardware Store',
            'tin_number' => '333444555',
            'description' => 'Duplicate business',
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
            'name' => 'PVC Pipe',
            'slug' => 'pvc-pipe',
            'description' => 'PVC pipe',
            'unit' => 'piece',
            'is_active' => true,
        ]);

        $request = BuyerRequest::create([
            'buyer_profile_id' => $buyer->id,
            'request_number' => 'REQ-API-008',
            'title' => 'Plumbing materials',
            'description' => 'Plumbing materials.',
            'status' => 'draft',
        ]);

        BuyerRequestItem::create([
            'buyer_request_id' => $request->id,
            'product_id' => $product->id,
            'quantity' => 10,
            'unit' => 'piece',
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson(
            "/api/buyer-requests/{$request->id}/items",
            [
                'product_id' => $product->id,
                'quantity' => 20,
                'unit' => 'piece',
            ]
        );

        $response
            ->assertUnprocessable()
            ->assertJsonStructure([
                'message',
                'errors',
            ]);
    }

    public function test_buyer_cannot_add_item_to_open_request(): void
    {
        $user = User::factory()->create();

        $buyer = BuyerProfile::create([
            'user_id' => $user->id,
            'business_name' => 'Published Hardware',
            'business_type' => 'Hardware Store',
            'tin_number' => '666777888',
            'description' => 'Published business',
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
            'request_number' => 'REQ-API-009',
            'title' => 'Roofing materials',
            'description' => 'Roofing materials.',
            'status' => 'open',
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson(
            "/api/buyer-requests/{$request->id}/items",
            [
                'product_id' => $product->id,
                'quantity' => 10,
                'unit' => 'piece',
            ]
        );

        $response->assertForbidden();
    }

    public function test_buyer_can_publish_a_draft_request(): void
    {
        $user = User::factory()->create();

        $buyer = BuyerProfile::create([
            'user_id' => $user->id,
            'business_name' => 'Publish Hardware',
            'business_type' => 'Hardware Store',
            'tin_number' => '999000111',
            'description' => 'Publish business',
            'status' => 'active',
        ]);

        $request = BuyerRequest::create([
            'buyer_profile_id' => $buyer->id,
            'request_number' => 'REQ-API-010',
            'title' => 'Publish request',
            'description' => 'Ready to publish.',
            'status' => 'draft',
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson(
            "/api/buyer-requests/{$request->id}/publish"
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'buyer_request.status',
                'open'
            );

        $this->assertDatabaseHas('buyer_requests', [
            'id' => $request->id,
            'status' => 'open',
        ]);
    }

    public function test_buyer_can_cancel_their_request(): void
    {
        $user = User::factory()->create();

        $buyer = BuyerProfile::create([
            'user_id' => $user->id,
            'business_name' => 'Cancel Hardware',
            'business_type' => 'Hardware Store',
            'tin_number' => '222111000',
            'description' => 'Cancel business',
            'status' => 'active',
        ]);

        $request = BuyerRequest::create([
            'buyer_profile_id' => $buyer->id,
            'request_number' => 'REQ-API-011',
            'title' => 'Cancel request',
            'description' => 'Request to cancel.',
            'status' => 'open',
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson(
            "/api/buyer-requests/{$request->id}/cancel"
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'buyer_request.status',
                'cancelled'
            );

        $this->assertDatabaseHas('buyer_requests', [
            'id' => $request->id,
            'status' => 'cancelled',
        ]);
    }

    public function test_another_buyer_cannot_publish_someone_elses_request(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        $buyer = BuyerProfile::create([
            'user_id' => $owner->id,
            'business_name' => 'Owner Hardware',
            'business_type' => 'Hardware Store',
            'tin_number' => '121212121',
            'description' => 'Owner business',
            'status' => 'active',
        ]);

        $request = BuyerRequest::create([
            'buyer_profile_id' => $buyer->id,
            'request_number' => 'REQ-API-012',
            'title' => 'Protected request',
            'description' => 'Protected.',
            'status' => 'draft',
        ]);

        Sanctum::actingAs($otherUser);

        $response = $this->postJson(
            "/api/buyer-requests/{$request->id}/publish"
        );

        $response->assertForbidden();
    }
}