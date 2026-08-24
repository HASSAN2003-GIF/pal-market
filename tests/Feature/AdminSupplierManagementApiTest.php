<?php

namespace Tests\Feature;

use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSupplierManagementApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_list_suppliers(): void
    {
        $response = $this->getJson('/api/admin/suppliers');

        $response->assertUnauthorized();
    }

    public function test_buyer_cannot_list_suppliers(): void
    {
        $buyer = User::factory()->create([
            'role' => 'buyer',
        ]);

        $response = $this->actingAs($buyer, 'sanctum')
            ->getJson('/api/admin/suppliers');

        $response->assertForbidden();
    }

    public function test_supplier_cannot_list_suppliers(): void
    {
        $supplierUser = User::factory()->create([
            'role' => 'supplier',
        ]);

        $response = $this->actingAs($supplierUser, 'sanctum')
            ->getJson('/api/admin/suppliers');

        $response->assertForbidden();
    }

    public function test_admin_can_list_suppliers(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $pendingUser = User::factory()->create([
            'name' => 'Pending Supplier',
            'email' => 'pending@example.com',
            'role' => 'supplier',
        ]);

        $pendingSupplier = Supplier::create([
            'user_id' => $pendingUser->id,
            'business_name' => 'Pending Hardware',
            'tin_number' => 'TIN-PENDING-001',
            'status' => 'pending',
        ]);

        $approvedUser = User::factory()->create([
            'name' => 'Approved Supplier',
            'email' => 'approved@example.com',
            'role' => 'supplier',
        ]);

        $approvedSupplier = Supplier::create([
            'user_id' => $approvedUser->id,
            'business_name' => 'Approved Hardware',
            'tin_number' => 'TIN-APPROVED-001',
            'status' => 'approved',
            'verified_at' => now(),
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/suppliers');

        $response
            ->assertOk()
            ->assertJsonStructure([
                'suppliers' => [
                    '*' => [
                        'id',
                        'business_name',
                        'tin_number',
                        'status',
                        'verified_at',
                        'user' => [
                            'id',
                            'name',
                            'email',
                        ],
                    ],
                ],
            ])
            ->assertJsonPath(
                'suppliers.0.id',
                $pendingSupplier->id
            )
            ->assertJsonFragment([
                'business_name' => 'Approved Hardware',
                'status' => 'approved',
            ]);
    }

    public function test_admin_can_view_a_supplier(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $supplierUser = User::factory()->create([
            'name' => 'Hassan Supplier',
            'email' => 'hassan-supplier@example.com',
            'role' => 'supplier',
        ]);

        $supplier = Supplier::create([
            'user_id' => $supplierUser->id,
            'business_name' => 'Hassan Building Supplies',
            'tin_number' => 'TIN-VIEW-001',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson("/api/admin/suppliers/{$supplier->id}");

        $response
            ->assertOk()
            ->assertJsonPath('supplier.id', $supplier->id)
            ->assertJsonPath(
                'supplier.business_name',
                'Hassan Building Supplies'
            )
            ->assertJsonPath('supplier.status', 'pending')
            ->assertJsonPath(
                'supplier.user.email',
                'hassan-supplier@example.com'
            );
    }

    public function test_admin_can_approve_pending_supplier(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $supplierUser = User::factory()->create([
            'role' => 'supplier',
        ]);

        $supplier = Supplier::create([
            'user_id' => $supplierUser->id,
            'business_name' => 'New Hardware Supplier',
            'tin_number' => 'TIN-APPROVE-001',
            'status' => 'pending',
            'verified_at' => null,
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/admin/suppliers/{$supplier->id}/approve");

        $response
            ->assertOk()
            ->assertJsonPath('supplier.id', $supplier->id)
            ->assertJsonPath('supplier.status', 'approved')
            ->assertJsonPath(
                'message',
                'Supplier approved successfully.'
            );

        $supplier->refresh();

        $this->assertEquals('approved', $supplier->status);
        $this->assertNotNull($supplier->verified_at);
    }

    public function test_admin_cannot_approve_already_approved_supplier(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $supplierUser = User::factory()->create([
            'role' => 'supplier',
        ]);

        $supplier = Supplier::create([
            'user_id' => $supplierUser->id,
            'business_name' => 'Approved Hardware',
            'tin_number' => 'TIN-APPROVE-002',
            'status' => 'approved',
            'verified_at' => now(),
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/admin/suppliers/{$supplier->id}/approve");

        $response
            ->assertUnprocessable()
            ->assertJson([
                'message' => 'Only pending suppliers can be approved.',
            ]);
    }

    public function test_admin_can_suspend_approved_supplier(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $supplierUser = User::factory()->create([
            'role' => 'supplier',
        ]);

        $supplier = Supplier::create([
            'user_id' => $supplierUser->id,
            'business_name' => 'Approved Hardware',
            'tin_number' => 'TIN-SUSPEND-001',
            'status' => 'approved',
            'verified_at' => now(),
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/admin/suppliers/{$supplier->id}/suspend");

        $response
            ->assertOk()
            ->assertJsonPath('supplier.status', 'suspended')
            ->assertJsonPath(
                'message',
                'Supplier suspended successfully.'
            );

        $this->assertDatabaseHas('suppliers', [
            'id' => $supplier->id,
            'status' => 'suspended',
        ]);
    }

    public function test_admin_can_reactivate_suspended_supplier(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $supplierUser = User::factory()->create([
            'role' => 'supplier',
        ]);

        $supplier = Supplier::create([
            'user_id' => $supplierUser->id,
            'business_name' => 'Suspended Hardware',
            'tin_number' => 'TIN-REACTIVATE-001',
            'status' => 'suspended',
            'verified_at' => now(),
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/admin/suppliers/{$supplier->id}/reactivate");

        $response
            ->assertOk()
            ->assertJsonPath('supplier.status', 'approved')
            ->assertJsonPath(
                'message',
                'Supplier reactivated successfully.'
            );

        $this->assertDatabaseHas('suppliers', [
            'id' => $supplier->id,
            'status' => 'approved',
        ]);
    }

    public function test_non_admin_cannot_approve_supplier(): void
    {
        $buyer = User::factory()->create([
            'role' => 'buyer',
        ]);

        $supplierUser = User::factory()->create([
            'role' => 'supplier',
        ]);

        $supplier = Supplier::create([
            'user_id' => $supplierUser->id,
            'business_name' => 'Pending Hardware',
            'tin_number' => 'TIN-AUTH-001',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($buyer, 'sanctum')
            ->postJson("/api/admin/suppliers/{$supplier->id}/approve");

        $response->assertForbidden();
    }

    public function test_admin_cannot_suspend_pending_supplier(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $supplierUser = User::factory()->create([
            'role' => 'supplier',
        ]);

        $supplier = Supplier::create([
            'user_id' => $supplierUser->id,
            'business_name' => 'Pending Hardware',
            'tin_number' => 'TIN-SUSPEND-002',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/admin/suppliers/{$supplier->id}/suspend");

        $response
            ->assertUnprocessable()
            ->assertJson([
                'message' => 'Only approved suppliers can be suspended.',
            ]);
    }

    public function test_admin_cannot_reactivate_approved_supplier(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $supplierUser = User::factory()->create([
            'role' => 'supplier',
        ]);

        $supplier = Supplier::create([
            'user_id' => $supplierUser->id,
            'business_name' => 'Approved Hardware',
            'tin_number' => 'TIN-REACTIVATE-002',
            'status' => 'approved',
            'verified_at' => now(),
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/admin/suppliers/{$supplier->id}/reactivate");

        $response
            ->assertUnprocessable()
            ->assertJson([
                'message' => 'Only suspended suppliers can be reactivated.',
            ]);
    }
}
