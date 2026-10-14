<?php

namespace App\Support;

use App\Models\ProductImage;

/**
 * Picks the picture shown for a product on cards and order lines: the
 * catalog's primary photo, otherwise a photo from an approved supplier's
 * active listing. Products without photos get null and the interface shows a
 * neutral placeholder (never a stock photo).
 */
class ProductImages
{
    /**
     * @param  iterable<int>  $productIds
     * @return array<int, array{thumb: string, large: string, alt: ?string, source: string}>
     */
    public static function covers(iterable $productIds): array
    {
        $ids = collect($productIds)->map(fn ($id) => (int) $id)->unique()->values();

        if ($ids->isEmpty()) {
            return [];
        }

        $covers = [];

        ProductImage::query()
            ->whereIn('product_id', $ids)
            ->orderByDesc('is_primary')->orderBy('sort_order')->orderBy('id')
            ->get()
            ->each(function (ProductImage $image) use (&$covers) {
                $covers[$image->product_id] ??= self::present($image, 'catalog');
            });

        $missing = $ids->reject(fn ($id) => isset($covers[$id]));

        if ($missing->isNotEmpty()) {
            ProductImage::query()
                ->select('product_images.*', 'supplier_products.product_id as listing_product_id')
                ->join('supplier_products', 'supplier_products.id', '=', 'product_images.supplier_product_id')
                ->join('suppliers', 'suppliers.id', '=', 'supplier_products.supplier_id')
                ->whereIn('supplier_products.product_id', $missing)
                ->where('supplier_products.is_active', true)
                ->where('suppliers.status', 'approved')
                ->orderByDesc('product_images.is_primary')->orderBy('product_images.sort_order')->orderBy('product_images.id')
                ->get()
                ->each(function (ProductImage $image) use (&$covers) {
                    $covers[(int) $image->listing_product_id] ??= self::present($image, 'supplier');
                });
        }

        return $covers;
    }

    public static function present(ProductImage $image, string $source): array
    {
        return [
            'thumb' => $image->urls['thumb'],
            'large' => $image->urls['large'],
            'alt' => $image->alt_text,
            'source' => $source,
        ];
    }
}
