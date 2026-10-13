<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\SupplierInventory;
use App\Models\SupplierLocation;
use App\Models\SupplierPrice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsMarketplace;
use Tests\TestCase;

class CatalogDiscoveryTest extends TestCase
{
    use BuildsMarketplace, RefreshDatabase;

    /** List a product by an approved supplier with a price and stock at a location in $region. */
    private function offer(Product $product, string $region, float $price, ?User $supplier = null, int $quantity = 100): User
    {
        $supplier ??= $this->makeSupplier();
        $supplierProduct = $supplier->supplier->supplierProducts()->create(['product_id' => $product->id, 'is_active' => true]);
        $location = SupplierLocation::create([
            'supplier_id' => $supplier->supplier->id,
            'name' => "Store {$region}",
            'address' => 'Main road',
            'region' => $region,
            'status' => 'active',
        ]);
        SupplierPrice::create(['supplier_product_id' => $supplierProduct->id, 'price' => $price, 'currency' => 'TZS', 'unit' => 'bag', 'is_active' => true]);
        SupplierInventory::create(['supplier_product_id' => $supplierProduct->id, 'supplier_location_id' => $location->id, 'quantity' => $quantity, 'is_available' => true]);

        return $supplier;
    }

    public function test_products_can_be_sorted_by_lowest_price_with_unpriced_products_last(): void
    {
        $cheap = $this->makeProduct(['name' => 'B Cement']);
        $dear = $this->makeProduct(['name' => 'A Cement']);
        $unpriced = $this->makeProduct(['name' => 'C Cement']);
        $this->offer($cheap, 'Arusha', 15000);
        $this->offer($dear, 'Arusha', 21000);

        $this->getJson('/api/products?sort=price_asc')
            ->assertOk()
            ->assertJsonPath('data.0.id', $cheap->id)
            ->assertJsonPath('data.1.id', $dear->id)
            ->assertJsonPath('data.2.id', $unpriced->id);

        $this->getJson('/api/products?sort=price_desc')
            ->assertJsonPath('data.0.id', $dear->id)
            ->assertJsonPath('data.1.id', $cheap->id)
            ->assertJsonPath('data.2.id', $unpriced->id);

        // Default and unknown values keep alphabetical order.
        $this->getJson('/api/products?sort=anything')
            ->assertJsonPath('data.0.id', $dear->id)
            ->assertJsonPath('data.2.id', $unpriced->id);
    }

    public function test_products_stocked_in_the_chosen_region_come_first_and_can_be_filtered(): void
    {
        $farAway = $this->makeProduct(['name' => 'A Nails']);
        $nearby = $this->makeProduct(['name' => 'Z Nails']);
        $this->offer($farAway, 'Arusha', 3000);
        $this->offer($nearby, 'Mwanza', 3500);

        $this->getJson('/api/products?region=Mwanza')
            ->assertOk()
            ->assertJsonPath('data.0.id', $nearby->id)
            ->assertJsonPath('data.0.local_offers_count', 1)
            ->assertJsonPath('data.1.local_offers_count', 0);

        $this->getJson('/api/products?region=mwanza&only_local=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $nearby->id);
    }

    public function test_unknown_regions_are_ignored_rather_than_hiding_everything(): void
    {
        $this->offer($this->makeProduct(), 'Arusha', 3000);
        $this->makeProduct();

        $this->getJson('/api/products?region=Atlantis&only_local=1')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonMissingPath('data.0.local_offers_count');
    }

    public function test_stock_out_or_unapproved_suppliers_do_not_count_as_local(): void
    {
        $product = $this->makeProduct();
        $this->offer($product, 'Mwanza', 3000, quantity: 0);
        $this->offer($product, 'Mwanza', 3000, $this->makeSupplier('pending'));

        $this->getJson('/api/products?region=Mwanza&only_local=1')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_product_offers_mark_and_prioritise_those_in_the_region(): void
    {
        $product = $this->makeProduct();
        $this->offer($product, 'Arusha', 1000);
        $local = $this->offer($product, 'Dodoma', 2000);

        $this->getJson("/api/products/{$product->id}?region=Dodoma")
            ->assertOk()
            ->assertJsonPath('data.supplier_offerings.0.supplier.id', $local->supplier->id)
            ->assertJsonPath('data.supplier_offerings.0.in_region', true)
            ->assertJsonPath('data.supplier_offerings.1.in_region', false);

        // Without a region: cheapest first, nothing marked local.
        $this->getJson("/api/products/{$product->id}")
            ->assertJsonPath('data.supplier_offerings.0.price', fn ($price) => (float) $price === 1000.0)
            ->assertJsonPath('data.supplier_offerings.0.in_region', false);
    }
}
