<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierInventory;
use App\Models\SupplierLocation;
use App\Models\SupplierPrice;
use App\Models\SupplierProduct;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductCatalogApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_list_active_products(): void
    {
        $category = Category::create([
            'name' => 'Cement',
            'slug' => 'cement',
            'is_active' => true,
        ]);

        $activeProduct = Product::create([
            'category_id' => $category->id,
            'name' => 'Cement 32.5R',
            'slug' => 'cement-32-5r',
            'unit' => 'bag',
            'is_active' => true,
        ]);

        Product::create([
            'category_id' => $category->id,
            'name' => 'Inactive Cement',
            'slug' => 'inactive-cement',
            'unit' => 'bag',
            'is_active' => false,
        ]);

        $response = $this->getJson('/api/products');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $activeProduct->id)
            ->assertJsonPath('data.0.name', 'Cement 32.5R');
    }

    public function test_guest_can_view_active_product(): void
    {
        $category = Category::create([
            'name' => 'Cement',
            'slug' => 'cement',
            'is_active' => true,
        ]);

        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Cement 32.5R',
            'slug' => 'cement-32-5r',
            'unit' => 'bag',
            'description' => 'General purpose construction cement.',
            'is_active' => true,
        ]);

        $response = $this->getJson("/api/products/{$product->id}");

        $response
            ->assertOk()
            ->assertJsonPath('data.id', $product->id)
            ->assertJsonPath('data.name', 'Cement 32.5R')
            ->assertJsonPath('data.unit', 'bag')
            ->assertJsonPath(
                'data.category.name',
                'Cement'
            );
    }

    public function test_inactive_product_is_not_visible_in_catalog(): void
    {
        $category = Category::create([
            'name' => 'Cement',
            'slug' => 'cement',
            'is_active' => true,
        ]);

        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Inactive Cement',
            'slug' => 'inactive-cement',
            'unit' => 'bag',
            'is_active' => false,
        ]);

        $this->getJson('/api/products')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->getJson("/api/products/{$product->id}")
            ->assertNotFound();
    }

    public function test_product_catalog_can_search_by_name(): void
    {
        $category = Category::create([
            'name' => 'Construction Materials',
            'slug' => 'construction-materials',
            'is_active' => true,
        ]);

        Product::create([
            'category_id' => $category->id,
            'name' => 'Cement 32.5R',
            'slug' => 'cement-32-5r',
            'unit' => 'bag',
            'is_active' => true,
        ]);

        Product::create([
            'category_id' => $category->id,
            'name' => 'Aluminium Window',
            'slug' => 'aluminium-window',
            'unit' => 'piece',
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/products?search=cement');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Cement 32.5R');
    }

    public function test_product_catalog_can_filter_by_category(): void
    {
        $cementCategory = Category::create([
            'name' => 'Cement',
            'slug' => 'cement',
            'is_active' => true,
        ]);

        $plumbingCategory = Category::create([
            'name' => 'Plumbing',
            'slug' => 'plumbing',
            'is_active' => true,
        ]);

        Product::create([
            'category_id' => $cementCategory->id,
            'name' => 'Cement 32.5R',
            'slug' => 'cement-32-5r',
            'unit' => 'bag',
            'is_active' => true,
        ]);

        Product::create([
            'category_id' => $plumbingCategory->id,
            'name' => 'PVC Pipe',
            'slug' => 'pvc-pipe',
            'unit' => 'piece',
            'is_active' => true,
        ]);

        $response = $this->getJson(
            "/api/products?category_id={$cementCategory->id}"
        );

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Cement 32.5R');
    }

    public function test_product_catalog_can_filter_by_brand(): void
    {
        $category = Category::create([
            'name' => 'Cement',
            'slug' => 'cement',
            'is_active' => true,
        ]);

        $brand = Brand::create([
            'name' => 'Twiga',
            'slug' => 'twiga',
            'is_active' => true,
        ]);

        Product::create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => 'Twiga Cement',
            'slug' => 'twiga-cement',
            'unit' => 'bag',
            'is_active' => true,
        ]);

        Product::create([
            'category_id' => $category->id,
            'name' => 'Generic Cement',
            'slug' => 'generic-cement',
            'unit' => 'bag',
            'is_active' => true,
        ]);

        $response = $this->getJson(
            "/api/products?brand_id={$brand->id}"
        );

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Twiga Cement');
    }

    public function test_product_detail_includes_available_supplier_offerings(): void
    {
        $user = User::factory()->create();

        $supplier = Supplier::create([
            'user_id' => $user->id,
            'business_name' => 'ABC Hardware',
            'tin_number' => 'TIN-001',
            'status' => 'approved',
        ]);

        $category = Category::create([
            'name' => 'Cement',
            'slug' => 'cement',
            'is_active' => true,
        ]);

        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Cement 32.5R',
            'slug' => 'cement-32-5r',
            'unit' => 'bag',
            'is_active' => true,
        ]);

        $supplierProduct = SupplierProduct::create([
            'supplier_id' => $supplier->id,
            'product_id' => $product->id,
            'supplier_sku' => 'ABC-CEM-001',
            'is_active' => true,
        ]);

        $location = SupplierLocation::create([
            'supplier_id' => $supplier->id,
            'name' => 'Main Store',
            'address' => 'Industrial Area',
            'region' => 'Dar es Salaam',
            'status' => 'active',
            'is_primary' => true,
        ]);

        SupplierPrice::create([
            'supplier_product_id' => $supplierProduct->id,
            'price' => 18500,
            'currency' => 'TZS',
            'unit' => 'bag',
            'is_active' => true,
        ]);

        SupplierInventory::create([
            'supplier_product_id' => $supplierProduct->id,
            'supplier_location_id' => $location->id,
            'quantity' => 500,
            'low_stock_threshold' => 20,
            'is_available' => true,
        ]);

        $response = $this->getJson("/api/products/{$product->id}");

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.supplier_offerings.0.supplier.business_name',
                'ABC Hardware'
            )
            ->assertJsonPath(
                'data.supplier_offerings.0.price',
                '18500.00'
            )
            ->assertJsonPath(
                'data.supplier_offerings.0.currency',
                'TZS'
            )
            ->assertJsonPath(
                'data.supplier_offerings.0.quantity',
                500
            )
            ->assertJsonPath(
                'data.supplier_offerings.0.location.region',
                'Dar es Salaam'
            );
    }

    public function test_inactive_supplier_offering_is_not_visible(): void
    {
        $user = User::factory()->create();

        $supplier = Supplier::create([
            'user_id' => $user->id,
            'business_name' => 'ABC Hardware',
            'tin_number' => 'TIN-002',
            'status' => 'approved',
        ]);

        $category = Category::create([
            'name' => 'Cement',
            'slug' => 'cement',
            'is_active' => true,
        ]);

        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Cement 32.5R',
            'slug' => 'cement-32-5r',
            'unit' => 'bag',
            'is_active' => true,
        ]);

        SupplierProduct::create([
            'supplier_id' => $supplier->id,
            'product_id' => $product->id,
            'supplier_sku' => 'ABC-CEM-002',
            'is_active' => false,
        ]);

        $response = $this->getJson("/api/products/{$product->id}");

        $response
            ->assertOk()
            ->assertJsonPath('data.supplier_offerings', []);
    }

    public function test_product_catalog_supports_pagination(): void
    {
        $category = Category::create([
            'name' => 'Construction Materials',
            'slug' => 'construction-materials',
            'is_active' => true,
        ]);

        for ($i = 1; $i <= 15; $i++) {
            Product::create([
                'category_id' => $category->id,
                'name' => "Product {$i}",
                'slug' => "product-{$i}",
                'unit' => 'piece',
                'is_active' => true,
            ]);
        }

        $response = $this->getJson('/api/products?per_page=10');

        $response
            ->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonStructure([
                'data',
                'links',
                'meta',
            ]);
    }
}