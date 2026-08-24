<?php

namespace Tests\Feature;

use App\Models\BuyerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminBuyerManagementApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_list_buyers(): void
    {
        $response = $this->getJson('/api/admin/buyers');

        $response->assertUnauthorized();
    }

    public function test_supplier_cannot_list_buyers(): void
    {
        $supplier = User::factory()->create([
            'role' => 'supplier',
        ]);

        $response = $this->actingAs($supplier, 'sanctum')
            ->getJson('/api/admin/buyers');

        $response->assertForbidden();
    }

    public function test_buyer_cannot_list_buyers(): void
    {
        $buyer = User::factory()->create([
            'role' => 'buyer',
        ]);

        $response = $this->actingAs($buyer, 'sanctum')
            ->getJson('/api/admin/buyers');

        $response->assertForbidden();
    }

    public function test_admin_can_list_buyers(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $buyerOne = User::factory()->create([
            'name' => 'Buyer One',
            'email' => 'buyer1@example.com',
            'role' => 'buyer',
        ]);

        $buyerTwo = User::factory()->create([
            'name' => 'Buyer Two',
            'email' => 'buyer2@example.com',
            'role' => 'buyer',
        ]);

        BuyerProfile::create([
            'user_id' => $buyerOne->id,
            'business_name' => 'Buyer One Hardware',
            'business_type' => 'Contractor',
            'tin_number' => 'TIN-BUYER-001',
            'status' => 'active',
        ]);

        BuyerProfile::create([
            'user_id' => $buyerTwo->id,
            'business_name' => 'Buyer Two Construction',
            'business_type' => 'Construction Company',
            'tin_number' => 'TIN-BUYER-002',
            'status' => 'suspended',
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/buyers');

        $response
            ->assertOk()
            ->assertJsonStructure([
                'buyers' => [
                    '*' => [
                        'id',
                        'user_id',
                        'business_name',
                        'business_type',
                        'tin_number',
                        'status',
                        'user' => [
                            'id',
                            'name',
                            'email',
                        ],
                    ],
                ],
            ])
            ->assertJsonPath('buyers.0.id', $buyerOne->id === $buyerTwo->id
                ? null
                : BuyerProfile::query()->orderBy('id')->first()->id)
            ->assertJsonFragment([
                'business_name' => 'Buyer One Hardware',
                'status' => 'active',
            ])
            ->assertJsonFragment([
                'business_name' => 'Buyer Two Construction',
                'status' => 'suspended',
            ]);
    }

    public function test_admin_can_view_a_buyer(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $buyer = User::factory()->create([
            'name' => 'Hassan Buyer',
            'email' => 'hassan-buyer@example.com',
            'role' => 'buyer',
        ]);

        $profile = BuyerProfile::create([
            'user_id' => $buyer->id,
            'business_name' => 'Hassan Construction Supplies',
            'business_type' => 'Contractor',
            'tin_number' => 'TIN-VIEW-BUYER-001',
            'description' => 'Construction materials buyer.',
            'status' => 'active',
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson("/api/admin/buyers/{$profile->id}");

        $response
            ->assertOk()
            ->assertJsonPath('buyer.id', $profile->id)
            ->assertJsonPath(
                'buyer.business_name',
                'Hassan Construction Supplies'
            )
            ->assertJsonPath('buyer.status', 'active')
            ->assertJsonPath(
                'buyer.user.email',
                'hassan-buyer@example.com'
            );
    }

    public function test_admin_can_suspend_active_buyer(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $buyer = User::factory()->create([
            'role' => 'buyer',
        ]);

        $profile = BuyerProfile::create([
            'user_id' => $buyer->id,
            'business_name' => 'Active Buyer',
            'status' => 'active',
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/admin/buyers/{$profile->id}/suspend");

        $response
            ->assertOk()
            ->assertJsonPath('buyer.status', 'suspended')
            ->assertJsonPath(
                'message',
                'Buyer suspended successfully.'
            );

        $this->assertDatabaseHas('buyer_profiles', [
            'id' => $profile->id,
            'status' => 'suspended',
        ]);
    }

    public function test_admin_can_reactivate_suspended_buyer(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $buyer = User::factory()->create([
            'role' => 'buyer',
        ]);

        $profile = BuyerProfile::create([
            'user_id' => $buyer->id,
            'business_name' => 'Suspended Buyer',
            'status' => 'suspended',
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/admin/buyers/{$profile->id}/reactivate");

        $response
            ->assertOk()
            ->assertJsonPath('buyer.status', 'active')
            ->assertJsonPath(
                'message',
                'Buyer reactivated successfully.'
            );

        $this->assertDatabaseHas('buyer_profiles', [
            'id' => $profile->id,
            'status' => 'active',
        ]);
    }

    public function test_admin_cannot_suspend_already_suspended_buyer(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $buyer = User::factory()->create([
            'role' => 'buyer',
        ]);

        $profile = BuyerProfile::create([
            'user_id' => $buyer->id,
            'business_name' => 'Suspended Buyer',
            'status' => 'suspended',
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/admin/buyers/{$profile->id}/suspend");

        $response
            ->assertUnprocessable()
            ->assertJson([
                'message' => 'Only active buyers can be suspended.',
            ]);
    }

    public function test_admin_cannot_reactivate_active_buyer(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $buyer = User::factory()->create([
            'role' => 'buyer',
        ]);

        $profile = BuyerProfile::create([
            'user_id' => $buyer->id,
            'business_name' => 'Active Buyer',
            'status' => 'active',
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/admin/buyers/{$profile->id}/reactivate");

        $response
            ->assertUnprocessable()
            ->assertJson([
                'message' => 'Only suspended buyers can be reactivated.',
            ]);
    }

    public function test_non_admin_cannot_suspend_buyer(): void
    {
        $supplier = User::factory()->create([
            'role' => 'supplier',
        ]);

        $buyer = User::factory()->create([
            'role' => 'buyer',
        ]);

        $profile = BuyerProfile::create([
            'user_id' => $buyer->id,
            'business_name' => 'Buyer Business',
            'status' => 'active',
        ]);

        $response = $this->actingAs($supplier, 'sanctum')
            ->postJson("/api/admin/buyers/{$profile->id}/suspend");

        $response->assertForbidden();
    }
}
