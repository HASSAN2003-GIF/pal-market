<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Services\AuditLogger;
use App\Support\Paginated;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Administrators own the shared product catalog that buyers request from and
 * suppliers price against. Products are deactivated rather than deleted once
 * referenced, so historical requests and orders stay intact.
 */
class AdminCatalogController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    public function products(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'category_id' => ['nullable', 'integer'],
            'status' => ['nullable', 'in:active,inactive'],
        ]);

        $products = Product::query()
            ->with(['category:id,name', 'brand:id,name', 'aliases:id,product_id,alias', 'images'])
            ->withCount('supplierProducts')
            ->when($validated['search'] ?? null, fn (Builder $q, $term) => $q->where(fn (Builder $w) => $w
                ->whereLike('name', Paginated::like($term))
                ->orWhereLike('name_sw', Paginated::like($term))))
            ->when($validated['category_id'] ?? null, fn (Builder $q, $id) => $q->where('category_id', $id))
            ->when($validated['status'] ?? null, fn (Builder $q, $status) => $q->where('is_active', $status === 'active'))
            ->orderBy('name')
            ->paginate(Paginated::perPage($request, 25, 100));

        return Paginated::json($products);
    }

    public function storeProduct(Request $request): JsonResponse
    {
        $validated = $request->validate($this->productRules());
        $aliases = Arr::pull($validated, 'aliases');

        $product = DB::transaction(function () use ($validated, $aliases) {
            $product = Product::create([...$validated, 'slug' => $this->uniqueSlug(Product::class, $validated['name'])]);
            $this->syncAliases($product, $aliases);

            return $product;
        });
        $this->audit->record('catalog.product_created', "Added product {$product->name}.", $product);

        return response()->json(['message' => __('Product added.'), 'product' => $product->load(['category', 'brand', 'aliases', 'images'])], 201);
    }

    public function updateProduct(Request $request, Product $product): JsonResponse
    {
        $validated = $request->validate($this->productRules($product));
        $aliases = Arr::pull($validated, 'aliases');

        DB::transaction(function () use ($product, $validated, $aliases) {
            $product->update($validated);
            $this->syncAliases($product, $aliases);
        });
        $this->audit->record('catalog.product_updated', "Updated product {$product->name}.", $product, [
            'changes' => $product->getChanges(),
            'aliases' => $aliases,
        ]);

        return response()->json(['message' => __('Product updated.'), 'product' => $product->load(['category', 'brand', 'aliases', 'images'])]);
    }

    public function categories(): JsonResponse
    {
        return response()->json([
            'categories' => Category::withCount('products')->orderBy('name')->get(),
            'brands' => Brand::withCount('products')->orderBy('name')->get(),
        ]);
    }

    public function storeCategory(Request $request): JsonResponse
    {
        return $this->storeTaxonomy($request, Category::class, 'categories', 'category');
    }

    public function updateCategory(Request $request, Category $category): JsonResponse
    {
        return $this->updateTaxonomy($request, $category, 'categories', 'category');
    }

    public function storeBrand(Request $request): JsonResponse
    {
        return $this->storeTaxonomy($request, Brand::class, 'brands', 'brand');
    }

    public function updateBrand(Request $request, Brand $brand): JsonResponse
    {
        return $this->updateTaxonomy($request, $brand, 'brands', 'brand');
    }

    private function storeTaxonomy(Request $request, string $model, string $table, string $label): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique($table, 'name')],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $record = $model::create([...$validated, 'slug' => $this->uniqueSlug($model, $validated['name'])]);
        $this->audit->record("catalog.{$label}_created", "Added {$label} {$record->name}.", $record);

        return response()->json(['message' => ucfirst($label).' added.', $label => $record], 201);
    }

    private function updateTaxonomy(Request $request, Model $record, string $table, string $label): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:120', Rule::unique($table, 'name')->ignore($record->id)],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $record->update($validated);
        $this->audit->record("catalog.{$label}_updated", "Updated {$label} {$record->name}.", $record);

        return response()->json(['message' => ucfirst($label).' updated.', $label => $record]);
    }

    private function productRules(?Product $product = null): array
    {
        $required = $product ? 'sometimes' : 'required';

        return [
            'name' => [$required, 'string', 'max:255'],
            'name_sw' => ['sometimes', 'nullable', 'string', 'max:255'],
            'aliases' => ['sometimes', 'nullable', 'array', 'max:20'],
            'aliases.*' => ['string', 'min:2', 'max:120'],
            'category_id' => [$required, 'integer', 'exists:categories,id'],
            'brand_id' => ['sometimes', 'nullable', 'integer', 'exists:brands,id'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'unit' => [$required, 'string', 'max:50'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Replaces a product's search aliases (null leaves them unchanged).
     */
    private function syncAliases(Product $product, ?array $aliases): void
    {
        if ($aliases === null) {
            return;
        }

        $clean = collect($aliases)
            ->map(fn ($alias) => trim(preg_replace('/\s+/', ' ', strip_tags((string) $alias))))
            ->filter(fn ($alias) => mb_strlen($alias) >= 2)
            ->unique(fn ($alias) => mb_strtolower($alias))
            ->values();

        $product->aliases()->whereNotIn('alias', $clean)->get()->each->delete();
        $existing = $product->aliases()->pluck('alias');
        $clean->reject(fn ($alias) => $existing->contains($alias))
            ->each(fn ($alias) => $product->aliases()->create(['alias' => $alias]));
    }

    private function uniqueSlug(string $model, string $name): string
    {
        $base = Str::slug($name) ?: 'item';
        $slug = $base;
        $i = 2;

        while ($model::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }
}
