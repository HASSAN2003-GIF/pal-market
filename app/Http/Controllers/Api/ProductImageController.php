<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\SupplierProduct;
use App\Services\AuditLogger;
use App\Services\ProductImageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Photo management for a supplier's own listing and, for administrators, for
 * catalog products. Ownership is checked on the server for every call.
 */
class ProductImageController extends Controller
{
    public function __construct(
        private ProductImageService $images,
        private AuditLogger $audit,
    ) {}

    // Supplier listing photos ------------------------------------------------

    public function storeForListing(Request $request, SupplierProduct $supplierProduct): JsonResponse
    {
        $this->authorize('update', $supplierProduct);

        return $this->store($request, $supplierProduct);
    }

    public function primaryForListing(SupplierProduct $supplierProduct, ProductImage $image): JsonResponse
    {
        $this->authorize('update', $supplierProduct);
        abort_unless($image->supplier_product_id === $supplierProduct->id, 404);

        return $this->primary($image, $supplierProduct);
    }

    public function destroyForListing(SupplierProduct $supplierProduct, ProductImage $image): JsonResponse
    {
        $this->authorize('update', $supplierProduct);
        abort_unless($image->supplier_product_id === $supplierProduct->id, 404);

        return $this->destroy($image, $supplierProduct);
    }

    // Catalog photos (administrators; route is behind the admin middleware) --

    public function storeForProduct(Request $request, Product $product): JsonResponse
    {
        return $this->store($request, $product);
    }

    public function primaryForProduct(Product $product, ProductImage $image): JsonResponse
    {
        abort_unless($image->product_id === $product->id, 404);

        return $this->primary($image, $product);
    }

    public function destroyForProduct(Product $product, ProductImage $image): JsonResponse
    {
        abort_unless($image->product_id === $product->id, 404);

        return $this->destroy($image, $product);
    }

    // -------------------------------------------------------------------------

    private function store(Request $request, Product|SupplierProduct $owner): JsonResponse
    {
        $request->validate([
            'images' => ['required', 'array', 'min:1', 'max:'.config('media.max_images_per_item')],
            'images.*' => ProductImageService::rules(),
            'alt_text' => ['nullable', 'string', 'max:160'],
        ]);

        $stored = $this->images->store($owner, $request->file('images'), $request->user(), $request->input('alt_text'));

        $this->audit->record('product_image.added', count($stored).' photo(s) added.', $owner, [
            'image_ids' => array_map(fn ($image) => $image->id, $stored),
        ]);

        return response()->json([
            'message' => __('Photos added.'),
            'images' => $owner->images()->get(),
        ], 201);
    }

    private function primary(ProductImage $image, Product|SupplierProduct $owner): JsonResponse
    {
        $this->images->makePrimary($image);

        return response()->json([
            'message' => __('Main photo updated.'),
            'images' => $owner->images()->get(),
        ]);
    }

    private function destroy(ProductImage $image, Product|SupplierProduct $owner): JsonResponse
    {
        $this->images->delete($image);
        $this->audit->record('product_image.removed', 'Photo removed.', $owner, ['image_id' => $image->id]);

        return response()->json([
            'message' => __('Photo removed.'),
            'images' => $owner->images()->get(),
        ]);
    }
}
