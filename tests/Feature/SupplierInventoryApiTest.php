<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierInventory;
use App\Models\SupplierLocation;
use App\Models\SupplierProduct;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SupplierInventoryApiTest extends TestCase
{
    use RefreshDatabase;

    private function createSupplierUser(string $status = 'approved'): array
    {
        $user = User::factory()->create();

        $supplier = Supplier::create([
            'user_id' => $user->id,
            'business_name' => 'Test Hardware',
            'tin_number' => 'TIN-'.$user->id,
            'description' => 'Test supplier',
            'status' => $status,
        ]);

        return [$user, $supplier];
    }

    private function createSupplierProduct(
        Supplier $supplier,
        ?Product $product = null
    ): SupplierProduct {
        $product ??= Product::create([
            'category_id' => Category::create([
                'name' => 'Construction Materials',
                'slug' => 'construction-materials',
            ])->id,
            'brand_id' => null,
            'name' => 'Cement '.uniqid(),
            'slug' => 'cement-'.uniqid(),
            'description' => 'Test cement product',
            'unit' => 'bag',
            'is_active' => true,
        ]);

        return SupplierProduct::create([
            'supplier_id' => $supplier->id,
            'product_id' => $product->id,
            'supplier_sku' => 'SKU-'.uniqid(),
            'description' => 'Supplier product',
            'is_active' => true,
        ]);
    }

    private function createSupplierLocation(
        Supplier $supplier,
        bool $active = true
    ): SupplierLocation {
        return SupplierLocation::create([
            'supplier_id' => $supplier->id,
            'name' => 'Main Warehouse',
            'address' => '123 Test Street',
            'region' => 'Dodoma',
            'district' => 'Dodoma Urban',
            'ward' => 'Kikuyu',
            'latitude' => '-6.1630',
            'longitude' => '35.7516',
            'phone' => '0712345678',
            'is_primary' => true,
            'status' => $active ? 'active' : 'inactive',
        ]);
    }

    public function test_unauthenticated_user_cannot_access_supplier_inventory(): void
    {
        $response = $this->getJson('/api/supplier-inventory');

        $response->assertStatus(401);
    }

    public function test_user_without_supplier_profile_cannot_access_supplier_inventory(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/supplier-inventory');

        $response->assertStatus(403);
    }

    public function test_approved_supplier_can_create_inventory(): void
    {
        [$user, $supplier] = $this->createSupplierUser();

        $supplierProduct = $this->createSupplierProduct($supplier);
        $supplierLocation = $this->createSupplierLocation($supplier);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/supplier-inventory', [
            'supplier_product_id' => $supplierProduct->id,
            'supplier_location_id' => $supplierLocation->id,
            'quantity' => 100,
            'low_stock_threshold' => 10,
            'is_available' => true,
        ]);

        $response
            ->assertStatus(201)
            ->assertJsonPath(
                'inventory.quantity',
                100
            )
            ->assertJsonPath(
                'inventory.low_stock_threshold',
                10
            )
            ->assertJsonPath(
                'inventory.is_available',
                true
            );

        $this->assertDatabaseHas('supplier_inventories', [
            'supplier_product_id' => $supplierProduct->id,
            'supplier_location_id' => $supplierLocation->id,
            'quantity' => 100,
            'low_stock_threshold' => 10,
            'is_available' => true,
        ]);
    }

    public function test_unapproved_supplier_cannot_create_inventory(): void
    {
        [$user, $supplier] = $this->createSupplierUser('pending');

        $supplierProduct = $this->createSupplierProduct($supplier);
        $supplierLocation = $this->createSupplierLocation($supplier);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/supplier-inventory', [
            'supplier_product_id' => $supplierProduct->id,
            'supplier_location_id' => $supplierLocation->id,
            'quantity' => 100,
        ]);

        $response->assertStatus(403);

        $this->assertDatabaseCount('supplier_inventories', 0);
    }

    public function test_supplier_cannot_create_inventory_for_another_suppliers_product(): void
    {
        [$user, $supplier] = $this->createSupplierUser();

        [, $otherSupplier] = $this->createSupplierUser();

        $supplierLocation = $this->createSupplierLocation($supplier);
        $otherSupplierProduct = $this->createSupplierProduct($otherSupplier);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/supplier-inventory', [
            'supplier_product_id' => $otherSupplierProduct->id,
            'supplier_location_id' => $supplierLocation->id,
            'quantity' => 100,
        ]);

        $response->assertStatus(403);
    }

    public function test_supplier_cannot_create_inventory_for_another_suppliers_location(): void
    {
        [$user, $supplier] = $this->createSupplierUser();

        [, $otherSupplier] = $this->createSupplierUser();

        $supplierProduct = $this->createSupplierProduct($supplier);
        $otherSupplierLocation = $this->createSupplierLocation($otherSupplier);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/supplier-inventory', [
            'supplier_product_id' => $supplierProduct->id,
            'supplier_location_id' => $otherSupplierLocation->id,
            'quantity' => 100,
        ]);

        $response->assertStatus(403);
    }

    public function test_supplier_cannot_create_inventory_for_inactive_product(): void
    {
        [$user, $supplier] = $this->createSupplierUser();

        $supplierProduct = $this->createSupplierProduct($supplier);
        $supplierProduct->update(['is_active' => false]);

        $supplierLocation = $this->createSupplierLocation($supplier);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/supplier-inventory', [
            'supplier_product_id' => $supplierProduct->id,
            'supplier_location_id' => $supplierLocation->id,
            'quantity' => 100,
        ]);

        $response->assertStatus(422);
    }

    public function test_supplier_cannot_create_inventory_for_inactive_location(): void
    {
        [$user, $supplier] = $this->createSupplierUser();

        $supplierProduct = $this->createSupplierProduct($supplier);
        $supplierLocation = $this->createSupplierLocation(
            $supplier,
            false
        );

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/supplier-inventory', [
            'supplier_product_id' => $supplierProduct->id,
            'supplier_location_id' => $supplierLocation->id,
            'quantity' => 100,
        ]);

        $response->assertStatus(422);
    }

    public function test_supplier_cannot_create_duplicate_product_location_inventory(): void
    {
        [$user, $supplier] = $this->createSupplierUser();

        $supplierProduct = $this->createSupplierProduct($supplier);
        $supplierLocation = $this->createSupplierLocation($supplier);

        SupplierInventory::create([
            'supplier_product_id' => $supplierProduct->id,
            'supplier_location_id' => $supplierLocation->id,
            'quantity' => 50,
            'low_stock_threshold' => 10,
            'is_available' => true,
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/supplier-inventory', [
            'supplier_product_id' => $supplierProduct->id,
            'supplier_location_id' => $supplierLocation->id,
            'quantity' => 100,
        ]);

        $response->assertStatus(422);

        $this->assertDatabaseCount('supplier_inventories', 1);
    }

    public function test_supplier_can_list_own_inventory(): void
    {
        [$user, $supplier] = $this->createSupplierUser();

        $supplierProduct = $this->createSupplierProduct($supplier);
        $supplierLocation = $this->createSupplierLocation($supplier);

        $inventory = SupplierInventory::create([
            'supplier_product_id' => $supplierProduct->id,
            'supplier_location_id' => $supplierLocation->id,
            'quantity' => 50,
            'low_stock_threshold' => 10,
            'is_available' => true,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/supplier-inventory');

        $response
            ->assertOk()
            ->assertJsonPath(
                'inventories.0.id',
                $inventory->id
            );
    }

    public function test_supplier_can_view_own_inventory(): void
    {
        [$user, $supplier] = $this->createSupplierUser();

        $supplierProduct = $this->createSupplierProduct($supplier);
        $supplierLocation = $this->createSupplierLocation($supplier);

        $inventory = SupplierInventory::create([
            'supplier_product_id' => $supplierProduct->id,
            'supplier_location_id' => $supplierLocation->id,
            'quantity' => 50,
            'low_stock_threshold' => 10,
            'is_available' => true,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson(
            "/api/supplier-inventory/{$inventory->id}"
        );

        $response
            ->assertOk()
            ->assertJsonPath('inventory.id', $inventory->id);
    }

    public function test_supplier_cannot_view_another_suppliers_inventory(): void
    {
        [$user, $supplier] = $this->createSupplierUser();

        [, $otherSupplier] = $this->createSupplierUser();

        $otherProduct = $this->createSupplierProduct($otherSupplier);
        $otherLocation = $this->createSupplierLocation($otherSupplier);

        $inventory = SupplierInventory::create([
            'supplier_product_id' => $otherProduct->id,
            'supplier_location_id' => $otherLocation->id,
            'quantity' => 50,
            'low_stock_threshold' => 10,
            'is_available' => true,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson(
            "/api/supplier-inventory/{$inventory->id}"
        );

        $response->assertStatus(403);
    }

    public function test_supplier_can_update_own_inventory(): void
    {
        [$user, $supplier] = $this->createSupplierUser();

        $supplierProduct = $this->createSupplierProduct($supplier);
        $supplierLocation = $this->createSupplierLocation($supplier);

        $inventory = SupplierInventory::create([
            'supplier_product_id' => $supplierProduct->id,
            'supplier_location_id' => $supplierLocation->id,
            'quantity' => 50,
            'low_stock_threshold' => 10,
            'is_available' => true,
        ]);

        Sanctum::actingAs($user);

        $response = $this->putJson(
            "/api/supplier-inventory/{$inventory->id}",
            [
                'quantity' => 200,
                'low_stock_threshold' => 20,
                'is_available' => false,
            ]
        );

        $response
            ->assertOk()
            ->assertJsonPath('inventory.quantity', 200)
            ->assertJsonPath('inventory.low_stock_threshold', 20)
            ->assertJsonPath('inventory.is_available', false);

        $this->assertDatabaseHas('supplier_inventories', [
            'id' => $inventory->id,
            'quantity' => 200,
            'low_stock_threshold' => 20,
            'is_available' => false,
        ]);
    }

    public function test_supplier_cannot_update_another_suppliers_inventory(): void
    {
        [$user] = $this->createSupplierUser();

        [, $otherSupplier] = $this->createSupplierUser();

        $otherProduct = $this->createSupplierProduct($otherSupplier);
        $otherLocation = $this->createSupplierLocation($otherSupplier);

        $inventory = SupplierInventory::create([
            'supplier_product_id' => $otherProduct->id,
            'supplier_location_id' => $otherLocation->id,
            'quantity' => 50,
            'low_stock_threshold' => 10,
            'is_available' => true,
        ]);

        Sanctum::actingAs($user);

        $response = $this->putJson(
            "/api/supplier-inventory/{$inventory->id}",
            [
                'quantity' => 200,
            ]
        );

        $response->assertStatus(403);
    }

    public function test_supplier_can_delete_own_inventory(): void
    {
        [$user, $supplier] = $this->createSupplierUser();

        $supplierProduct = $this->createSupplierProduct($supplier);
        $supplierLocation = $this->createSupplierLocation($supplier);

        $inventory = SupplierInventory::create([
            'supplier_product_id' => $supplierProduct->id,
            'supplier_location_id' => $supplierLocation->id,
            'quantity' => 50,
            'low_stock_threshold' => 10,
            'is_available' => true,
        ]);

        Sanctum::actingAs($user);

        $response = $this->deleteJson(
            "/api/supplier-inventory/{$inventory->id}"
        );

        $response->assertOk();

        $this->assertDatabaseMissing('supplier_inventories', [
            'id' => $inventory->id,
        ]);
    }

    public function test_supplier_cannot_delete_another_suppliers_inventory(): void
    {
        [$user] = $this->createSupplierUser();

        [, $otherSupplier] = $this->createSupplierUser();

        $otherProduct = $this->createSupplierProduct($otherSupplier);
        $otherLocation = $this->createSupplierLocation($otherSupplier);

        $inventory = SupplierInventory::create([
            'supplier_product_id' => $otherProduct->id,
            'supplier_location_id' => $otherLocation->id,
            'quantity' => 50,
            'low_stock_threshold' => 10,
            'is_available' => true,
        ]);

        Sanctum::actingAs($user);

        $response = $this->deleteJson(
            "/api/supplier-inventory/{$inventory->id}"
        );

        $response->assertStatus(403);
    }

    public function test_quantity_cannot_be_negative(): void
    {
        [$user, $supplier] = $this->createSupplierUser();

        $supplierProduct = $this->createSupplierProduct($supplier);
        $supplierLocation = $this->createSupplierLocation($supplier);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/supplier-inventory', [
            'supplier_product_id' => $supplierProduct->id,
            'supplier_location_id' => $supplierLocation->id,
            'quantity' => -1,
        ]);

        $response->assertStatus(422);
    }

    public function test_low_stock_threshold_cannot_be_negative(): void
    {
        [$user, $supplier] = $this->createSupplierUser();

        $supplierProduct = $this->createSupplierProduct($supplier);
        $supplierLocation = $this->createSupplierLocation($supplier);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/supplier-inventory', [
            'supplier_product_id' => $supplierProduct->id,
            'supplier_location_id' => $supplierLocation->id,
            'quantity' => 50,
            'low_stock_threshold' => -1,
        ]);

        $response->assertStatus(422);
    }
}
