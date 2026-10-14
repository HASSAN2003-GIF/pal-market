<?php

namespace App\Services;

use App\Models\Product;
use App\Models\SupplierPrice;
use App\Support\ProductImages;
use App\Support\Regions;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;

class ProductCatalogService
{
    /**
     * How the last list() search matched, for the response (see ProductSearch).
     */
    public ?array $lastSearch = null;

    public function __construct(private ProductSearch $productSearch) {}

    public function list(array $filters = []): LengthAwarePaginator
    {
        $perPage = min(
            max((int) ($filters['per_page'] ?? 15), 1),
            50
        );

        // Offers from approved suppliers with an active listing.
        $activeOffers = fn ($q) => $q
            ->where('supplier_products.is_active', true)
            ->whereHas('supplier', fn ($s) => $s->where('status', 'approved'));

        // Same offers, limited to stock held at an active location in the region.
        $region = Regions::normalise($filters['region'] ?? null);
        $localOffers = fn ($q) => $activeOffers($q)->whereHas('inventories', fn ($i) => $i
            ->where('is_available', true)
            ->where('quantity', '>', 0)
            ->whereHas('supplierLocation', fn ($l) => $l->where('status', 'active')->where('region', $region)));

        $query = Product::query()
            ->with([
                'category',
                'brand',
            ])
            ->withCount(['supplierProducts as offers_count' => $activeOffers])
            ->when($region, fn ($q) => $q->withCount(['supplierProducts as local_offers_count' => $localOffers]))
            ->addSelect([
                // Lowest currently effective price across those offers.
                'from_price' => SupplierPrice::query()
                    ->selectRaw('min(supplier_prices.price)')
                    ->join('supplier_products', 'supplier_products.id', '=', 'supplier_prices.supplier_product_id')
                    ->join('suppliers', 'suppliers.id', '=', 'supplier_products.supplier_id')
                    ->whereColumn('supplier_products.product_id', 'products.id')
                    ->where('supplier_products.is_active', true)
                    ->where('suppliers.status', 'approved')
                    ->where('supplier_prices.is_active', true)
                    ->where(fn ($q) => $q->whereNull('effective_from')->orWhere('effective_from', '<=', now()))
                    ->where(fn ($q) => $q->whereNull('effective_until')->orWhere('effective_until', '>', now())),
            ])
            ->where('is_active', true);

        $this->lastSearch = null;

        if (is_string($filters['search'] ?? null) && trim($filters['search']) !== '') {
            $this->lastSearch = $this->productSearch->search(mb_substr($filters['search'], 0, 100));
            $query->whereIn('products.id', $this->lastSearch['ids'] ?: [0]);
        }

        if (! empty($filters['category_id'])) {
            $query->where(
                'category_id',
                $filters['category_id']
            );
        }

        if (! empty($filters['brand_id'])) {
            $query->where(
                'brand_id',
                $filters['brand_id']
            );
        }

        if ($region && filter_var($filters['only_local'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $query->whereHas('supplierProducts', $localOffers);
        }

        match ($filters['sort'] ?? null) {
            // Unpriced products last in both directions.
            'price_asc' => $this->orderByPrice($query, 'asc'),
            'price_desc' => $this->orderByPrice($query, 'desc'),
            'name' => null,
            // Default: best search match first, then products stocked in the
            // person's region.
            default => $this->orderByRelevance($query, $region),
        };

        $page = $query
            ->orderBy('name')
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();

        $covers = ProductImages::covers(collect($page->items())->pluck('id'));
        foreach ($page->items() as $product) {
            $product->setAttribute('image', $covers[$product->id] ?? null);
        }

        return $page;
    }

    /**
     * Unpriced products last in both directions. PostgreSQL cannot use a
     * select alias inside an expression, so it gets NULLS LAST instead.
     */
    private function orderByPrice($query, string $direction): void
    {
        if ($query->getConnection()->getDriverName() === 'pgsql') {
            $query->orderByRaw("from_price {$direction} nulls last");

            return;
        }

        $query->orderByRaw('from_price is null')->orderBy('from_price', $direction);
    }

    private function orderByRelevance($query, ?string $region): void
    {
        $ids = $this->lastSearch['ids'] ?? [];

        if ($ids !== []) {
            $cases = collect($ids)->map(fn ($id, $rank) => 'when '.((int) $id).' then '.((int) $rank))->implode(' ');
            $query->orderByRaw("case products.id {$cases} else ".count($ids).' end');
        }

        if ($region) {
            $query->orderByDesc('local_offers_count');
        }
    }

    public function find(int $productId): Product
    {
        $product = Product::query()
            ->with([
                'category',
                'brand',
                'images',
                'supplierProducts' => function ($query) {
                    $query
                        ->where('is_active', true)
                        ->whereHas('supplier', function ($query) {
                            $query->where('status', 'approved');
                        })
                        ->with([
                            'supplier',
                            'images',
                            'prices' => function ($query) {
                                $query
                                    ->where('is_active', true)
                                    ->where(function ($query) {
                                        $query
                                            ->whereNull('effective_from')
                                            ->orWhere(
                                                'effective_from',
                                                '<=',
                                                now()
                                            );
                                    })
                                    ->where(function ($query) {
                                        $query
                                            ->whereNull('effective_until')
                                            ->orWhere(
                                                'effective_until',
                                                '>',
                                                now()
                                            );
                                    })
                                    ->orderBy('price');
                            },
                            'inventories' => function ($query) {
                                $query
                                    ->where('is_available', true)
                                    ->where('quantity', '>', 0)
                                    ->whereHas('supplierLocation', function ($query) {
                                        $query->where(
                                            'status',
                                            'active'
                                        );
                                    })
                                    ->with('supplierLocation');
                            },
                        ]);
                },
            ])
            ->where('is_active', true)
            ->find($productId);

        if (! $product) {
            throw (new ModelNotFoundException)
                ->setModel(Product::class, [$productId]);
        }

        return $product;
    }

    public function supplierOfferings(Product $product, ?string $region = null): Collection
    {
        $region = Regions::normalise($region);

        return $product->supplierProducts
            ->filter(function ($supplierProduct) {
                return $supplierProduct->prices->isNotEmpty()
                    && $supplierProduct->inventories->isNotEmpty();
            })
            ->map(function ($supplierProduct) use ($region) {
                $price = $supplierProduct->prices->first();
                $inventory = ($region
                    ? $supplierProduct->inventories->first(fn ($i) => $i->supplierLocation->region === $region)
                    : null) ?? $supplierProduct->inventories->first();

                return [
                    'supplier_product_id' => $supplierProduct->id,
                    'supplier' => [
                        'id' => $supplierProduct->supplier->id,
                        'business_name' => $supplierProduct
                            ->supplier
                            ->business_name,
                    ],
                    'supplier_sku' => $supplierProduct->supplier_sku,
                    'images' => $supplierProduct->images->map(fn ($image) => ProductImages::present($image, 'supplier'))->values(),
                    'price' => $price->price,
                    'currency' => $price->currency,
                    'unit' => $price->unit,
                    'quantity' => $inventory->quantity,
                    'location' => [
                        'id' => $inventory
                            ->supplierLocation
                            ->id,
                        'name' => $inventory
                            ->supplierLocation
                            ->name,
                        'address' => $inventory
                            ->supplierLocation
                            ->address,
                        'region' => $inventory
                            ->supplierLocation
                            ->region,
                        'district' => $inventory
                            ->supplierLocation
                            ->district,
                        'ward' => $inventory
                            ->supplierLocation
                            ->ward,
                    ],
                    'in_region' => $region !== null && $inventory->supplierLocation->region === $region,
                ];
            })
            // Offers in the person's region first, then cheapest.
            ->sortBy([['in_region', 'desc'], fn ($a, $b) => $a['price'] <=> $b['price']])
            ->values();
    }
}
