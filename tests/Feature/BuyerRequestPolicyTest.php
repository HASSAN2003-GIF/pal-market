<?php

namespace Tests\Feature;

use App\Models\BuyerProfile;
use App\Models\BuyerRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BuyerRequestPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_buyer_can_view_their_own_request(): void
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
            'request_number' => 'REQ-POLICY-001',
            'title' => 'Cement requirement',
            'description' => 'Need cement.',
            'status' => 'draft',
        ]);

        $this->assertTrue(
            $user->can('view', $request)
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
            'tin_number' => '111222333',
            'description' => 'Owner business',
            'status' => 'active',
        ]);

        $request = BuyerRequest::create([
            'buyer_profile_id' => $buyer->id,
            'request_number' => 'REQ-POLICY-002',
            'title' => 'Cement requirement',
            'description' => 'Need cement.',
            'status' => 'draft',
        ]);

        $this->assertFalse(
            $otherUser->can('view', $request)
        );
    }

    public function test_buyer_can_update_their_own_draft_request(): void
    {
        $user = User::factory()->create();

        $buyer = BuyerProfile::create([
            'user_id' => $user->id,
            'business_name' => 'Draft Hardware',
            'business_type' => 'Hardware Store',
            'tin_number' => '444555666',
            'description' => 'Draft business',
            'status' => 'active',
        ]);

        $request = BuyerRequest::create([
            'buyer_profile_id' => $buyer->id,
            'request_number' => 'REQ-POLICY-003',
            'title' => 'Draft request',
            'description' => 'Draft request.',
            'status' => 'draft',
        ]);

        $this->assertTrue(
            $user->can('update', $request)
        );
    }

    public function test_buyer_cannot_update_their_own_published_request(): void
    {
        $user = User::factory()->create();

        $buyer = BuyerProfile::create([
            'user_id' => $user->id,
            'business_name' => 'Published Hardware',
            'business_type' => 'Hardware Store',
            'tin_number' => '777888999',
            'description' => 'Published business',
            'status' => 'active',
        ]);

        $request = BuyerRequest::create([
            'buyer_profile_id' => $buyer->id,
            'request_number' => 'REQ-POLICY-004',
            'title' => 'Published request',
            'description' => 'Published request.',
            'status' => 'open',
        ]);

        $this->assertFalse(
            $user->can('update', $request)
        );
    }

    public function test_user_without_buyer_profile_cannot_create_request(): void
    {
        $user = User::factory()->create();

        $this->assertFalse(
            $user->can('create', BuyerRequest::class)
        );
    }

    public function test_buyer_can_create_a_request(): void
    {
        $user = User::factory()->create();

        BuyerProfile::create([
            'user_id' => $user->id,
            'business_name' => 'New Buyer Hardware',
            'business_type' => 'Hardware Store',
            'tin_number' => '999888777',
            'description' => 'New buyer',
            'status' => 'active',
        ]);

        $this->assertTrue(
            $user->can('create', BuyerRequest::class)
        );
    }
}
