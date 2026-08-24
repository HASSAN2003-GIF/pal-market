<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;

class ProductCatalogService
{
    public function list(array $filters = []): LengthAwarePaginator
    {
        $perPage = min(
            max((int) ($filters['per_page'] ?? 15), 1),
            50
        );

        $query = Product::query()
            ->with([
                'category',
                'brand',
            ])
            ->where('is_active', true);

        if (! empty($filters['search'])) {
            $search = trim($filters['search']);

            $query->where(function ($query) use ($search) {
                $query
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
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

        return $query
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function find(int $productId): Product
    {
        $product = Product::query()
            ->with([
                'category',
                'brand',
                'supplierProducts' => function ($query) {
                    $query
                        ->where('is_active', true)
                        ->whereHas('supplier', function ($query) {
                            $query->where('status', 'approved');
                        })
                        ->with([
                            'supplier',
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

    public function supplierOfferings(Product $product): Collection
    {
        return $product->supplierProducts
            ->filter(function ($supplierProduct) {
                return $supplierProduct->prices->isNotEmpty()
                    && $supplierProduct->inventories->isNotEmpty();
            })
            ->map(function ($supplierProduct) {
                $price = $supplierProduct->prices->first();
                $inventory = $supplierProduct->inventories->first();

                return [
                    'supplier_product_id' => $supplierProduct->id,
                    'supplier' => [
                        'id' => $supplierProduct->supplier->id,
                        'business_name' => $supplierProduct
                            ->supplier
                            ->business_name,
                    ],
                    'supplier_sku' => $supplierProduct->supplier_sku,
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
                ];
            })
            ->values();
    }
}
