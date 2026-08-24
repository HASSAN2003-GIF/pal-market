<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ProductCatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductCatalogController extends Controller
{
    public function __construct(
        private ProductCatalogService $productCatalogService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $products = $this->productCatalogService->list(
            $request->only([
                'search',
                'category_id',
                'brand_id',
                'per_page',
            ])
        );

        return response()->json([
            'data' => $products->items(),

            'links' => [
                'first' => $products->url(1),
                'last' => $products->url($products->lastPage()),
                'prev' => $products->previousPageUrl(),
                'next' => $products->nextPageUrl(),
            ],

            'meta' => [
                'current_page' => $products->currentPage(),
                'from' => $products->firstItem(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'to' => $products->lastItem(),
                'total' => $products->total(),
            ],
        ]);
    }

    public function show(int $product): JsonResponse
    {
        $product = $this->productCatalogService->find($product);

        return response()->json([
            'data' => [
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
                'description' => $product->description,
                'unit' => $product->unit,
                'category' => [
                    'id' => $product->category->id,
                    'name' => $product->category->name,
                    'slug' => $product->category->slug,
                ],
                'brand' => $product->brand ? [
                    'id' => $product->brand->id,
                    'name' => $product->brand->name,
                    'slug' => $product->brand->slug,
                ] : null,
                'supplier_offerings' => $this
                    ->productCatalogService
                    ->supplierOfferings($product),
            ],
        ]);
    }
}
