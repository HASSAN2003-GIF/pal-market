<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductProposal;
use App\Models\SupplierProduct;
use App\Services\AuditLogger;
use App\Support\Paginated;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Administrators review products proposed by suppliers: approve as a new
 * catalog product, link to an existing product, or reject with a reason.
 */
class AdminProductProposalController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::in(ProductProposal::STATUSES)],
        ]);

        $proposals = ProductProposal::query()
            ->with(['supplier:id,business_name', 'category:id,name', 'product:id,name', 'reviewer:id,name'])
            ->when($validated['status'] ?? 'pending', fn (Builder $q, $status) => $q->where('status', $status))
            ->oldest()
            ->paginate(Paginated::perPage($request, 25, 100));

        return Paginated::json($proposals, extra: [
            'pending_count' => ProductProposal::query()->where('status', 'pending')->count(),
        ]);
    }

    public function approve(Request $request, ProductProposal $proposal): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'name' => ['required_without:product_id', 'nullable', 'string', 'max:120'],
            'name_sw' => ['nullable', 'string', 'max:120'],
            'category_id' => ['required_without:product_id', 'nullable', 'integer', 'exists:categories,id'],
            'brand_id' => ['nullable', 'integer', 'exists:brands,id'],
            'unit' => ['required_without:product_id', 'nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:5000'],
        ]);

        $product = DB::transaction(function () use ($proposal, $validated, $request) {
            $proposal = ProductProposal::query()->lockForUpdate()->findOrFail($proposal->id);
            $this->ensurePending($proposal);

            $product = isset($validated['product_id'])
                ? Product::findOrFail($validated['product_id'])
                : Product::create([
                    'name' => $validated['name'],
                    'name_sw' => $validated['name_sw'] ?? null,
                    'category_id' => $validated['category_id'],
                    'brand_id' => $validated['brand_id'] ?? null,
                    'unit' => $validated['unit'],
                    'description' => $validated['description'] ?? null,
                    'slug' => $this->uniqueSlug($validated['name']),
                    'is_active' => true,
                ]);

            $proposal->forceFill([
                'status' => 'approved',
                'product_id' => $product->id,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
            ])->save();

            // Put the product straight into the proposing supplier's list.
            if ($proposal->supplier?->status === 'approved') {
                SupplierProduct::query()->firstOrCreate(
                    ['supplier_id' => $proposal->supplier_id, 'product_id' => $product->id],
                    ['is_active' => true],
                );
            }

            return $product;
        });

        $this->audit->record(
            'product_proposal.approved',
            isset($validated['product_id'])
                ? "Linked proposal \"{$proposal->name}\" to existing product {$product->name}."
                : "Approved proposal \"{$proposal->name}\" as new product {$product->name}.",
            $proposal,
            ['product_id' => $product->id],
        );

        return response()->json([
            'message' => __('Product approved.'),
            'proposal' => $proposal->refresh()->load(['product:id,name', 'supplier:id,business_name']),
        ]);
    }

    public function reject(Request $request, ProductProposal $proposal): JsonResponse
    {
        $validated = $request->validate([
            'review_note' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        DB::transaction(function () use ($proposal, $validated, $request) {
            $locked = ProductProposal::query()->lockForUpdate()->findOrFail($proposal->id);
            $this->ensurePending($locked);

            $locked->forceFill([
                'status' => 'rejected',
                'review_note' => $validated['review_note'],
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
            ])->save();
        });

        $this->audit->record('product_proposal.rejected', "Rejected proposal \"{$proposal->name}\".", $proposal, [
            'reason' => $validated['review_note'],
        ]);

        return response()->json([
            'message' => __('Proposal rejected.'),
            'proposal' => $proposal->refresh(),
        ]);
    }

    private function ensurePending(ProductProposal $proposal): void
    {
        if ($proposal->status !== 'pending') {
            throw ValidationException::withMessages([
                'status' => __('This proposal has already been reviewed.'),
            ]);
        }
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'item';
        $slug = $base;
        $i = 2;

        while (Product::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }
}
