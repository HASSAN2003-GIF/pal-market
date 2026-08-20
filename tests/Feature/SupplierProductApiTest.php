<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SupplierProductApiTest extends TestCase
{
    use RefreshDatabase;

    private function createProduct(): Product
    {
        $category = Category::create([
            'name' => 'Construction Materials',
            'slug' => 'construction-materials',
            'description' => 'Construction materials',
            'is_active' => true,
        ]);

        return Product::create([
            'category_id' => $category->id,
            'name' => 'Cement 50kg',
            'slug' => 'cement-50kg',
            'description' => '50kg cement bag',
            'unit' => 'bag',
            'is_active' => true,
        ]);
    }

    private function createSupplier(
        string $status = 'approved'
    ): array {
        $user = User::factory()->create();

        $supplier = Supplier::create([
            'user_id' => $user->id,
            'business_name' => 'Hassan Hardware',
            'tin_number' => fake()->unique()->numerify('#########'),
            'description' => 'Construction materials supplier',
            'status' => $status,
        ]);

        return [$user, $supplier];
    }

    public function test_unauthenticated_user_cannot_access_supplier_products(): void
    {
        $response = $this->getJson('/api/supplier-products');

        $response->assertStatus(401);
    }

    public function test_supplier_can_add_product_to_catalog(): void
    {
        [$user, $supplier] = $this->createSupplier();
        $product = $this->createProduct();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/supplier-products', [
            'product_id' => $product->id,
            'supplier_sku' => 'CEM-50',
            'description' => 'Our 50kg cement bag',
        ]);

        $response
            ->assertStatus(201)
            ->assertJsonPath(
                'supplier_product.product_id',
                $product->id
            );

        $this->assertDatabaseHas('supplier_products', [
            'supplier_id' => $supplier->id,
            'product_id' => $product->id,
            'supplier_sku' => 'CEM-50',
        ]);
    }

    public function test_unapproved_supplier_cannot_add_product(): void
    {
        [$user] = $this->createSupplier('pending');
        $product = $this->createProduct();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/supplier-products', [
            'product_id' => $product->id,
        ]);

        $response->assertStatus(403);
    }

    public function test_supplier_cannot_add_same_product_twice(): void
    {
        [$user] = $this->createSupplier();
        $product = $this->createProduct();

        Sanctum::actingAs($user);

        $this->postJson('/api/supplier-products', [
            'product_id' => $product->id,
        ])->assertStatus(201);

        $this->postJson('/api/supplier-products', [
            'product_id' => $product->id,
        ])->assertStatus(422);
    }

    public function test_supplier_can_list_own_products(): void
    {
        [$user, $supplier] = $this->createSupplier();
        $product = $this->createProduct();

        $supplier->supplierProducts()->create([
            'product_id' => $product->id,
            'supplier_sku' => 'CEM-50',
            'description' => '50kg cement',
            'is_active' => true,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/supplier-products');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'supplier_products');
    }

    public function test_supplier_can_view_own_product(): void
    {
        [$user, $supplier] = $this->createSupplier();
        $product = $this->createProduct();

        $supplierProduct = $supplier->supplierProducts()->create([
            'product_id' => $product->id,
            'supplier_sku' => 'CEM-50',
            'description' => '50kg cement',
            'is_active' => true,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson(
            "/api/supplier-products/{$supplierProduct->id}"
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'supplier_product.id',
                $supplierProduct->id
            );
    }

    public function test_supplier_cannot_view_another_suppliers_product(): void
    {
        [$userOne, $supplierOne] = $this->createSupplier();
        [, $supplierTwo] = $this->createSupplier();

        $product = $this->createProduct();

        $supplierProduct = $supplierTwo->supplierProducts()->create([
            'product_id' => $product->id,
            'supplier_sku' => 'OTHER-001',
            'description' => 'Another supplier product',
            'is_active' => true,
        ]);

        Sanctum::actingAs($userOne);

        $response = $this->getJson(
            "/api/supplier-products/{$supplierProduct->id}"
        );

        $response->assertStatus(403);
    }

    public function test_supplier_can_update_own_product(): void
    {
        [$user, $supplier] = $this->createSupplier();
        $product = $this->createProduct();

        $supplierProduct = $supplier->supplierProducts()->create([
            'product_id' => $product->id,
            'supplier_sku' => 'OLD-SKU',
            'description' => 'Old description',
            'is_active' => true,
        ]);

        Sanctum::actingAs($user);

        $response = $this->putJson(
            "/api/supplier-products/{$supplierProduct->id}",
            [
                'supplier_sku' => 'NEW-SKU',
                'description' => 'Updated description',
                'is_active' => false,
            ]
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'supplier_product.supplier_sku',
                'NEW-SKU'
            );

        $this->assertDatabaseHas('supplier_products', [
            'id' => $supplierProduct->id,
            'supplier_sku' => 'NEW-SKU',
            'is_active' => false,
        ]);
    }

    public function test_supplier_can_delete_own_product(): void
    {
        [$user, $supplier] = $this->createSupplier();
        $product = $this->createProduct();

        $supplierProduct = $supplier->supplierProducts()->create([
            'product_id' => $product->id,
            'supplier_sku' => 'CEM-50',
            'description' => '50kg cement',
            'is_active' => true,
        ]);

        Sanctum::actingAs($user);

        $response = $this->deleteJson(
            "/api/supplier-products/{$supplierProduct->id}"
        );

        $response->assertOk();

        $this->assertDatabaseMissing('supplier_products', [
            'id' => $supplierProduct->id,
        ]);
    }
}
