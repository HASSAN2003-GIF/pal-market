<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierPrice;
use App\Models\SupplierProduct;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SupplierPriceApiTest extends TestCase
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

    private function createSupplierProduct(
        Supplier $supplier
    ): SupplierProduct {
        $product = $this->createProduct();

        return $supplier->supplierProducts()->create([
            'product_id' => $product->id,
            'supplier_sku' => 'CEM-50',
            'description' => '50kg cement',
            'is_active' => true,
        ]);
    }

    public function test_unauthenticated_user_cannot_access_supplier_prices(): void
    {
        $response = $this->getJson('/api/supplier-prices');

        $response->assertStatus(401);
    }

    public function test_approved_supplier_can_create_price(): void
    {
        [$user, $supplier] = $this->createSupplier();
        $supplierProduct = $this->createSupplierProduct($supplier);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/supplier-prices', [
            'supplier_product_id' => $supplierProduct->id,
            'price' => '18000.00',
            'currency' => 'TZS',
            'unit' => 'bag',
            'is_active' => true,
        ]);

        $response
            ->assertStatus(201)
            ->assertJsonPath(
                'supplier_price.supplier_product_id',
                $supplierProduct->id
            );

        $this->assertDatabaseHas('supplier_prices', [
            'supplier_product_id' => $supplierProduct->id,
            'price' => '18000.00',
            'currency' => 'TZS',
            'unit' => 'bag',
            'is_active' => true,
        ]);
    }

    public function test_unapproved_supplier_cannot_create_price(): void
    {
        [$user, $supplier] = $this->createSupplier('pending');
        $supplierProduct = $this->createSupplierProduct($supplier);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/supplier-prices', [
            'supplier_product_id' => $supplierProduct->id,
            'price' => '18000.00',
            'currency' => 'TZS',
            'unit' => 'bag',
        ]);

        $response->assertStatus(403);
    }

    public function test_supplier_cannot_create_price_for_another_suppliers_product(): void
    {
        [$userOne] = $this->createSupplier();
        [, $supplierTwo] = $this->createSupplier();

        $supplierProduct = $this->createSupplierProduct($supplierTwo);

        Sanctum::actingAs($userOne);

        $response = $this->postJson('/api/supplier-prices', [
            'supplier_product_id' => $supplierProduct->id,
            'price' => '18000.00',
            'currency' => 'TZS',
            'unit' => 'bag',
        ]);

        $response->assertStatus(403);
    }

    public function test_supplier_cannot_create_price_for_inactive_product(): void
    {
        [$user, $supplier] = $this->createSupplier();
        $supplierProduct = $this->createSupplierProduct($supplier);

        $supplierProduct->update([
            'is_active' => false,
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/supplier-prices', [
            'supplier_product_id' => $supplierProduct->id,
            'price' => '18000.00',
            'currency' => 'TZS',
            'unit' => 'bag',
        ]);

        $response->assertStatus(422);
    }

    public function test_price_must_be_greater_than_zero(): void
    {
        [$user, $supplier] = $this->createSupplier();
        $supplierProduct = $this->createSupplierProduct($supplier);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/supplier-prices', [
            'supplier_product_id' => $supplierProduct->id,
            'price' => '0',
            'currency' => 'TZS',
            'unit' => 'bag',
        ]);

        $response->assertStatus(422);
    }

    public function test_effective_until_must_be_after_effective_from(): void
    {
        [$user, $supplier] = $this->createSupplier();
        $supplierProduct = $this->createSupplierProduct($supplier);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/supplier-prices', [
            'supplier_product_id' => $supplierProduct->id,
            'price' => '18000.00',
            'currency' => 'TZS',
            'unit' => 'bag',
            'effective_from' => '2026-09-30 00:00:00',
            'effective_until' => '2026-09-01 00:00:00',
        ]);

        $response->assertStatus(422);
    }

    public function test_supplier_cannot_create_overlapping_active_price_period(): void
    {
        [$user, $supplier] = $this->createSupplier();
        $supplierProduct = $this->createSupplierProduct($supplier);

        SupplierPrice::create([
            'supplier_product_id' => $supplierProduct->id,
            'price' => '18000.00',
            'currency' => 'TZS',
            'unit' => 'bag',
            'is_active' => true,
            'effective_from' => '2026-08-20 00:00:00',
            'effective_until' => '2026-09-30 23:59:59',
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/supplier-prices', [
            'supplier_product_id' => $supplierProduct->id,
            'price' => '19000.00',
            'currency' => 'TZS',
            'unit' => 'bag',
            'is_active' => true,
            'effective_from' => '2026-09-15 00:00:00',
            'effective_until' => '2026-10-15 23:59:59',
        ]);

        $response->assertStatus(422);
    }

    public function test_supplier_can_list_own_prices(): void
    {
        [$user, $supplier] = $this->createSupplier();
        $supplierProduct = $this->createSupplierProduct($supplier);

        SupplierPrice::create([
            'supplier_product_id' => $supplierProduct->id,
            'price' => '18000.00',
            'currency' => 'TZS',
            'unit' => 'bag',
            'is_active' => true,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/supplier-prices');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'supplier_prices');
    }

    public function test_supplier_can_view_own_price(): void
    {
        [$user, $supplier] = $this->createSupplier();
        $supplierProduct = $this->createSupplierProduct($supplier);

        $supplierPrice = SupplierPrice::create([
            'supplier_product_id' => $supplierProduct->id,
            'price' => '18000.00',
            'currency' => 'TZS',
            'unit' => 'bag',
            'is_active' => true,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson(
            "/api/supplier-prices/{$supplierPrice->id}"
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'supplier_price.id',
                $supplierPrice->id
            );
    }

    public function test_supplier_cannot_view_another_suppliers_price(): void
    {
        [$userOne] = $this->createSupplier();
        [, $supplierTwo] = $this->createSupplier();

        $supplierProduct = $this->createSupplierProduct($supplierTwo);

        $supplierPrice = SupplierPrice::create([
            'supplier_product_id' => $supplierProduct->id,
            'price' => '18000.00',
            'currency' => 'TZS',
            'unit' => 'bag',
            'is_active' => true,
        ]);

        Sanctum::actingAs($userOne);

        $response = $this->getJson(
            "/api/supplier-prices/{$supplierPrice->id}"
        );

        $response->assertStatus(403);
    }

    public function test_supplier_can_update_own_price(): void
    {
        [$user, $supplier] = $this->createSupplier();
        $supplierProduct = $this->createSupplierProduct($supplier);

        $supplierPrice = SupplierPrice::create([
            'supplier_product_id' => $supplierProduct->id,
            'price' => '18000.00',
            'currency' => 'TZS',
            'unit' => 'bag',
            'is_active' => true,
        ]);

        Sanctum::actingAs($user);

        $response = $this->putJson(
            "/api/supplier-prices/{$supplierPrice->id}",
            [
                'price' => '19500.00',
                'currency' => 'TZS',
                'unit' => 'bag',
                'is_active' => true,
            ]
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'supplier_price.price',
                '19500.00'
            );

        $this->assertDatabaseHas('supplier_prices', [
            'id' => $supplierPrice->id,
            'price' => '19500.00',
        ]);
    }

    public function test_supplier_cannot_update_another_suppliers_price(): void
    {
        [$userOne] = $this->createSupplier();
        [, $supplierTwo] = $this->createSupplier();

        $supplierProduct = $this->createSupplierProduct($supplierTwo);

        $supplierPrice = SupplierPrice::create([
            'supplier_product_id' => $supplierProduct->id,
            'price' => '18000.00',
            'currency' => 'TZS',
            'unit' => 'bag',
            'is_active' => true,
        ]);

        Sanctum::actingAs($userOne);

        $response = $this->putJson(
            "/api/supplier-prices/{$supplierPrice->id}",
            [
                'price' => '19500.00',
            ]
        );

        $response->assertStatus(403);
    }

    public function test_supplier_can_delete_own_price(): void
    {
        [$user, $supplier] = $this->createSupplier();
        $supplierProduct = $this->createSupplierProduct($supplier);

        $supplierPrice = SupplierPrice::create([
            'supplier_product_id' => $supplierProduct->id,
            'price' => '18000.00',
            'currency' => 'TZS',
            'unit' => 'bag',
            'is_active' => true,
        ]);

        Sanctum::actingAs($user);

        $response = $this->deleteJson(
            "/api/supplier-prices/{$supplierPrice->id}"
        );

        $response->assertOk();

        $this->assertDatabaseMissing('supplier_prices', [
            'id' => $supplierPrice->id,
        ]);
    }
}