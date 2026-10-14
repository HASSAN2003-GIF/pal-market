<?php

namespace Tests\Concerns;

use App\Models\BuyerProfile;
use App\Models\BuyerRequest;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Small factories for marketplace scenarios used by the newer tests.
 */
trait BuildsMarketplace
{
    protected function makeProduct(array $overrides = []): Product
    {
        $category = Category::firstOrCreate(['slug' => 'test-materials'], ['name' => 'Test Materials']);
        $name = $overrides['name'] ?? 'Product '.Str::random(6);

        return Product::create([
            'category_id' => $category->id,
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'unit' => 'bag',
            'is_active' => true,
            ...$overrides,
        ]);
    }

    protected function makeBuyer(string $status = 'active'): User
    {
        $user = User::factory()->create(['role' => 'buyer']);
        BuyerProfile::create(['user_id' => $user->id, 'business_name' => 'Buyer '.$user->id, 'status' => $status]);

        return $user->fresh();
    }

    protected function makeSupplier(string $status = 'approved'): User
    {
        $user = User::factory()->create(['role' => 'supplier']);
        Supplier::create([
            'user_id' => $user->id,
            'business_name' => 'Supplier '.$user->id,
            'tin_number' => 'TIN-'.$user->id,
            'status' => $status,
        ]);

        return $user->fresh();
    }

    protected function makeAdmin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    /**
     * An open request with one item, plus a supplier who stocks that product.
     *
     * @return array{buyer: User, supplier: User, request: BuyerRequest, product: Product}
     */
    protected function openRequestWithSupplier(): array
    {
        $buyer = $this->makeBuyer();
        $supplier = $this->makeSupplier();
        $product = $this->makeProduct();

        $supplier->supplier->supplierProducts()->create(['product_id' => $product->id]);

        $request = BuyerRequest::create([
            'buyer_profile_id' => $buyer->buyerProfile->id,
            'request_number' => 'REQ-T-'.Str::upper(Str::random(8)),
            'title' => 'Site materials',
            'status' => 'open',
            'published_at' => now(),
            'expires_at' => now()->addDays(7),
        ]);
        $request->items()->create(['product_id' => $product->id, 'quantity' => 40, 'unit' => 'bag']);

        return compact('buyer', 'supplier', 'request', 'product');
    }
}
