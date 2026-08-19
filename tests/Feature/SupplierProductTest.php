<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_supplier_can_be_linked_to_a_product(): void
    {
        $user = User::factory()->create();

        $supplier = Supplier::create([
            'user_id' => $user->id,
            'business_name' => 'Hassan Hardware',
            'tin_number' => '123456789',
            'description' => 'Construction materials supplier',
            'status' => 'approved',
        ]);

        $category = Category::create([
            'name' => 'Construction Materials',
            'slug' => 'construction-materials',
            'description' => 'Construction materials',
            'is_active' => true,
        ]);

        $brand = Brand::create([
            'name' => 'Example Brand',
            'slug' => 'example-brand',
            'description' => 'Example brand',
            'is_active' => true,
        ]);

        $product = Product::create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => 'Cement 50kg',
            'slug' => 'cement-50kg',
            'description' => '50kg cement bag',
            'unit' => 'bag',
            'is_active' => true,
        ]);

        $supplierProduct = SupplierProduct::create([
            'supplier_id' => $supplier->id,
            'product_id' => $product->id,
            'supplier_sku' => 'CEM-50',
            'description' => '50kg cement',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('supplier_products', [
            'id' => $supplierProduct->id,
            'supplier_id' => $supplier->id,
            'product_id' => $product->id,
        ]);

        $this->assertTrue(
            $supplier->products->contains($product)
        );
    }

    public function test_product_can_find_its_supplier(): void
    {
        $user = User::factory()->create();

        $supplier = Supplier::create([
            'user_id' => $user->id,
            'business_name' => 'Hassan Hardware',
            'tin_number' => '987654321',
            'description' => 'Construction materials supplier',
            'status' => 'approved',
        ]);

        $category = Category::create([
            'name' => 'Plumbing',
            'slug' => 'plumbing',
            'description' => 'Plumbing materials',
            'is_active' => true,
        ]);

        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'PVC Pipe 1 Inch',
            'slug' => 'pvc-pipe-1-inch',
            'description' => '1 inch PVC pipe',
            'unit' => 'piece',
            'is_active' => true,
        ]);

        SupplierProduct::create([
            'supplier_id' => $supplier->id,
            'product_id' => $product->id,
            'supplier_sku' => 'PVC-1',
            'description' => '1 inch PVC pipe',
            'is_active' => true,
        ]);

        $this->assertTrue(
            $product->suppliers->contains($supplier)
        );
    }

    public function test_supplier_cannot_register_the_same_product_twice(): void
    {
        $user = User::factory()->create();

        $supplier = Supplier::create([
            'user_id' => $user->id,
            'business_name' => 'Duplicate Test Hardware',
            'tin_number' => '555555555',
            'description' => 'Test supplier',
            'status' => 'approved',
        ]);

        $category = Category::create([
            'name' => 'Electrical',
            'slug' => 'electrical',
            'description' => 'Electrical materials',
            'is_active' => true,
        ]);

        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Electrical Cable',
            'slug' => 'electrical-cable',
            'description' => 'Electrical cable',
            'unit' => 'meter',
            'is_active' => true,
        ]);

        SupplierProduct::create([
            'supplier_id' => $supplier->id,
            'product_id' => $product->id,
            'supplier_sku' => 'CAB-001',
            'description' => 'Electrical cable',
            'is_active' => true,
        ]);

        $this->expectException(QueryException::class);

        SupplierProduct::create([
            'supplier_id' => $supplier->id,
            'product_id' => $product->id,
            'supplier_sku' => 'CAB-002',
            'description' => 'Same product again',
            'is_active' => true,
        ]);
    }
}
