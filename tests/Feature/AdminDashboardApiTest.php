<?php

namespace Tests\Feature;

use App\Models\BuyerProfile;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_access_admin_dashboard(): void
    {
        $response = $this->getJson('/api/admin/dashboard');

        $response->assertUnauthorized();
    }

    public function test_buyer_cannot_access_admin_dashboard(): void
    {
        $buyer = User::factory()->create([
            'role' => 'buyer',
        ]);

        $response = $this->actingAs($buyer, 'sanctum')
            ->getJson('/api/admin/dashboard');

        $response->assertForbidden();
    }

    public function test_supplier_cannot_access_admin_dashboard(): void
    {
        $supplierUser = User::factory()->create([
            'role' => 'supplier',
        ]);

        $response = $this->actingAs($supplierUser, 'sanctum')
            ->getJson('/api/admin/dashboard');

        $response->assertForbidden();
    }

    public function test_admin_can_view_dashboard_statistics(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $buyerOne = User::factory()->create([
            'role' => 'buyer',
        ]);

        $buyerTwo = User::factory()->create([
            'role' => 'buyer',
        ]);

        BuyerProfile::create([
            'user_id' => $buyerOne->id,
            'business_name' => 'Buyer One Company',
            'phone' => '+255700000001',
        ]);

        BuyerProfile::create([
            'user_id' => $buyerTwo->id,
            'business_name' => 'Buyer Two Company',
            'phone' => '+255700000002',
        ]);

        $supplierUserOne = User::factory()->create([
            'role' => 'supplier',
        ]);

        $supplierUserTwo = User::factory()->create([
            'role' => 'supplier',
        ]);

        $supplierUserThree = User::factory()->create([
            'role' => 'supplier',
        ]);

        $supplierUserFour = User::factory()->create([
            'role' => 'supplier',
        ]);

        Supplier::create([
            'user_id' => $supplierUserOne->id,
            'business_name' => 'Pending Hardware',
            'tin_number' => 'TIN-DASH-001',
            'status' => 'pending',
        ]);

        Supplier::create([
            'user_id' => $supplierUserTwo->id,
            'business_name' => 'Approved Hardware',
            'tin_number' => 'TIN-DASH-002',
            'status' => 'approved',
            'verified_at' => now(),
        ]);

        Supplier::create([
            'user_id' => $supplierUserThree->id,
            'business_name' => 'Suspended Hardware',
            'tin_number' => 'TIN-DASH-003',
            'status' => 'suspended',
            'verified_at' => now(),
        ]);

        Supplier::create([
            'user_id' => $supplierUserFour->id,
            'business_name' => 'Another Approved Hardware',
            'tin_number' => 'TIN-DASH-004',
            'status' => 'approved',
            'verified_at' => now(),
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/dashboard');

        $response
            ->assertOk()
            ->assertJsonStructure([
                'statistics' => [
                    'users',
                    'buyers',
                    'suppliers' => [
                        'total',
                        'pending',
                        'approved',
                        'suspended',
                    ],
                    'products',
                    'buyer_requests',
                    'quotations',
                    'purchase_orders',
                ],
            ])
            ->assertJsonPath('statistics.users', 7)
            ->assertJsonPath('statistics.buyers', 2)
            ->assertJsonPath('statistics.suppliers.total', 4)
            ->assertJsonPath('statistics.suppliers.pending', 1)
            ->assertJsonPath('statistics.suppliers.approved', 2)
            ->assertJsonPath('statistics.suppliers.suspended', 1);
    }

    public function test_admin_dashboard_counts_only_users_by_role(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        User::factory()->count(3)->create([
            'role' => 'buyer',
        ]);

        User::factory()->count(2)->create([
            'role' => 'supplier',
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/dashboard');

        $response
            ->assertOk()
            ->assertJsonPath('statistics.users', 6)
            ->assertJsonPath('statistics.buyers', 3)
            ->assertJsonPath('statistics.suppliers.total', 0);
    }
}
