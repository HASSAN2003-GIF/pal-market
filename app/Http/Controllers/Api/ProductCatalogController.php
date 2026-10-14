<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Services\ProductCatalogService;
use App\Services\ProductSearch;
use App\Support\ProductImages;
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
                'sort',
                'region',
                'only_local',
            ])
        );

        $search = $this->productCatalogService->lastSearch;

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

            // How the search words matched: exact, close (after a spelling
            // correction), related (only some words matched) or none.
            'search' => $search ? [
                'mode' => $search['mode'],
                'suggestion' => $search['suggestion'],
            ] : null,
        ]);
    }

    /**
     * Type-ahead suggestions for the search box and the supplier product
     * picker. Matches names, Kiswahili names, aliases and common synonyms.
     */
    public function suggest(Request $request, ProductSearch $search): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:1', 'max:100'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:15'],
        ]);

        $result = $search->suggest($validated['q'], $validated['limit'] ?? 8);
        $ids = collect($result['items'])->pluck('id');

        $products = Product::query()
            ->with('category:id,name')
            ->whereIn('id', $ids)
            ->where('is_active', true)
            ->get(['id', 'name', 'name_sw', 'unit', 'category_id'])
            ->keyBy('id');
        $covers = ProductImages::covers($ids);

        return response()->json([
            'mode' => $result['mode'],
            'data' => collect($result['items'])
                ->filter(fn ($item) => $products->has($item['id']))
                ->map(fn ($item) => [
                    'id' => $item['id'],
                    'name' => $products[$item['id']]->name,
                    'name_sw' => $products[$item['id']]->name_sw,
                    'unit' => $products[$item['id']]->unit,
                    'category' => $products[$item['id']]->category?->only(['id', 'name']),
                    'matched' => $item['matched'],
                    'image' => $covers[$item['id']]['thumb'] ?? null,
                ])
                ->values(),
        ]);
    }

    /**
     * Active categories and brands for marketplace filters.
     */
    public function filters(): JsonResponse
    {
        return response()->json([
            'categories' => Category::query()
                ->where('is_active', true)
                ->withCount(['products' => fn ($q) => $q->where('is_active', true)])
                ->orderBy('name')
                ->get(['id', 'name', 'slug']),
            'brands' => Brand::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'slug']),
        ]);
    }

    public function show(Request $request, int $product): JsonResponse
    {
        $product = $this->productCatalogService->find($product);

        // Catalog photos first, then photos from suppliers' listings.
        $images = $product->images
            ->map(fn ($image) => ProductImages::present($image, 'catalog'))
            ->concat($product->supplierProducts->flatMap(
                fn ($listing) => $listing->images->map(fn ($image) => [
                    ...ProductImages::present($image, 'supplier'),
                    'supplier' => $listing->supplier->business_name,
                ])
            ))
            ->values();

        return response()->json([
            'data' => [
                'id' => $product->id,
                'name' => $product->name,
                'name_sw' => $product->name_sw,
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
                'images' => $images,
                'supplier_offerings' => $this
                    ->productCatalogService
                    ->supplierOfferings($product, $request->query('region')),
            ],
        ]);
    }
}
