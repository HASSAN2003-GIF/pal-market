<?php

namespace Tests\Feature;

use App\Models\Supplier;
use App\Models\SupplierLocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SupplierLocationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_access_supplier_locations(): void
    {
        $response = $this->getJson('/api/supplier-locations');

        $response->assertStatus(401);
    }

    public function test_user_without_supplier_profile_cannot_access_supplier_locations(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/supplier-locations');

        $response->assertStatus(403);
    }

    public function test_approved_supplier_can_create_location(): void
    {
        $supplier = $this->createSupplier('approved');

        Sanctum::actingAs($supplier->user);

        $response = $this->postJson('/api/supplier-locations', [
            'name' => 'Main Warehouse',
            'address' => 'Plot 10, Nyerere Road',
            'region' => 'Dodoma',
            'district' => 'Dodoma Urban',
            'ward' => 'Kikuyu',
            'latitude' => -6.1630,
            'longitude' => 35.7516,
            'phone' => '+255700000000',
            'is_primary' => true,
        ]);

        $response
            ->assertStatus(201)
            ->assertJsonPath(
                'supplier_location.name',
                'Main Warehouse'
            );

        $this->assertDatabaseHas('supplier_locations', [
            'supplier_id' => $supplier->id,
            'name' => 'Main Warehouse',
            'is_primary' => true,
        ]);
    }

    public function test_unapproved_supplier_cannot_create_location(): void
    {
        $supplier = $this->createSupplier('pending');

        Sanctum::actingAs($supplier->user);

        $response = $this->postJson('/api/supplier-locations', [
            'name' => 'Pending Warehouse',
            'address' => 'Test Address',
            'region' => 'Dodoma',
        ]);

        $response->assertStatus(403);

        $this->assertDatabaseMissing('supplier_locations', [
            'supplier_id' => $supplier->id,
            'name' => 'Pending Warehouse',
        ]);
    }

    public function test_supplier_can_list_own_locations(): void
    {
        $supplier = $this->createSupplier('approved');

        SupplierLocation::create([
            'supplier_id' => $supplier->id,
            'name' => 'Main Warehouse',
            'address' => 'Main Address',
            'region' => 'Dodoma',
            'is_primary' => true,
            'status' => 'active',
        ]);

        SupplierLocation::create([
            'supplier_id' => $supplier->id,
            'name' => 'Branch Warehouse',
            'address' => 'Branch Address',
            'region' => 'Dodoma',
            'is_primary' => false,
            'status' => 'active',
        ]);

        Sanctum::actingAs($supplier->user);

        $response = $this->getJson('/api/supplier-locations');

        $response
            ->assertOk()
            ->assertJsonCount(2, 'supplier_locations');
    }

    public function test_supplier_can_view_own_location(): void
    {
        $supplier = $this->createSupplier('approved');

        $location = $this->createLocation($supplier);

        Sanctum::actingAs($supplier->user);

        $response = $this->getJson(
            "/api/supplier-locations/{$location->id}"
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'supplier_location.id',
                $location->id
            );
    }

    public function test_supplier_cannot_view_another_suppliers_location(): void
    {
        $supplierA = $this->createSupplier(
            'approved',
            'Supplier A'
        );

        $supplierB = $this->createSupplier(
            'approved',
            'Supplier B'
        );

        $location = $this->createLocation($supplierB);

        Sanctum::actingAs($supplierA->user);

        $response = $this->getJson(
            "/api/supplier-locations/{$location->id}"
        );

        $response->assertStatus(403);
    }

    public function test_supplier_can_update_own_location(): void
    {
        $supplier = $this->createSupplier('approved');

        $location = $this->createLocation($supplier);

        Sanctum::actingAs($supplier->user);

        $response = $this->putJson(
            "/api/supplier-locations/{$location->id}",
            [
                'name' => 'Updated Warehouse',
                'address' => 'Updated Address',
                'region' => 'Dar es Salaam',
                'district' => 'Ilala',
                'ward' => 'Kariakoo',
                'phone' => '+255711111111',
                'is_primary' => true,
                'status' => 'active',
            ]
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'supplier_location.name',
                'Updated Warehouse'
            );

        $this->assertDatabaseHas('supplier_locations', [
            'id' => $location->id,
            'name' => 'Updated Warehouse',
            'region' => 'Dar es Salaam',
            'is_primary' => true,
        ]);
    }

    public function test_supplier_cannot_update_another_suppliers_location(): void
    {
        $supplierA = $this->createSupplier(
            'approved',
            'Supplier A'
        );

        $supplierB = $this->createSupplier(
            'approved',
            'Supplier B'
        );

        $location = $this->createLocation($supplierB);

        Sanctum::actingAs($supplierA->user);

        $response = $this->putJson(
            "/api/supplier-locations/{$location->id}",
            [
                'name' => 'Hacked Location',
                'address' => 'Hacked Address',
                'region' => 'Dodoma',
            ]
        );

        $response->assertStatus(403);

        $this->assertDatabaseMissing('supplier_locations', [
            'id' => $location->id,
            'name' => 'Hacked Location',
        ]);
    }

    public function test_supplier_can_delete_own_location(): void
    {
        $supplier = $this->createSupplier('approved');

        $location = $this->createLocation($supplier);

        Sanctum::actingAs($supplier->user);

        $response = $this->deleteJson(
            "/api/supplier-locations/{$location->id}"
        );

        $response->assertOk();

        $this->assertDatabaseMissing('supplier_locations', [
            'id' => $location->id,
        ]);
    }

    public function test_supplier_cannot_delete_another_suppliers_location(): void
    {
        $supplierA = $this->createSupplier(
            'approved',
            'Supplier A'
        );

        $supplierB = $this->createSupplier(
            'approved',
            'Supplier B'
        );

        $location = $this->createLocation($supplierB);

        Sanctum::actingAs($supplierA->user);

        $response = $this->deleteJson(
            "/api/supplier-locations/{$location->id}"
        );

        $response->assertStatus(403);

        $this->assertDatabaseHas('supplier_locations', [
            'id' => $location->id,
        ]);
    }

    public function test_creating_new_primary_location_removes_previous_primary(): void
    {
        $supplier = $this->createSupplier('approved');

        $firstLocation = $this->createLocation(
            $supplier,
            'First Warehouse',
            true
        );

        Sanctum::actingAs($supplier->user);

        $response = $this->postJson('/api/supplier-locations', [
            'name' => 'Second Warehouse',
            'address' => 'Second Address',
            'region' => 'Dodoma',
            'is_primary' => true,
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('supplier_locations', [
            'id' => $firstLocation->id,
            'is_primary' => false,
        ]);

        $this->assertDatabaseHas('supplier_locations', [
            'supplier_id' => $supplier->id,
            'name' => 'Second Warehouse',
            'is_primary' => true,
        ]);
    }

    private function createSupplier(
        string $status,
        string $businessName = 'Test Hardware'
    ): Supplier {
        $user = User::factory()->create();

        return Supplier::create([
            'user_id' => $user->id,
            'business_name' => $businessName,
            'tin_number' => fake()->unique()->numerify('#########'),
            'description' => 'Construction materials supplier',
            'status' => $status,
        ]);
    }

    private function createLocation(
        Supplier $supplier,
        string $name = 'Main Warehouse',
        bool $isPrimary = false
    ): SupplierLocation {
        return SupplierLocation::create([
            'supplier_id' => $supplier->id,
            'name' => $name,
            'address' => 'Test Address',
            'region' => 'Dodoma',
            'district' => 'Dodoma Urban',
            'ward' => 'Kikuyu',
            'latitude' => -6.1630,
            'longitude' => 35.7516,
            'phone' => '+255700000000',
            'is_primary' => $isPrimary,
            'status' => 'active',
        ]);
    }
}
