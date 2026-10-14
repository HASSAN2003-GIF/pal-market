<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProductProposal;
use App\Services\AuditLogger;
use App\Services\ProductSearch;
use App\Support\SearchText;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Suppliers ask for a product that is not in the catalog yet. Nothing is
 * shown to buyers until an administrator reviews the proposal.
 */
class ProductProposalController extends Controller
{
    private const MAX_PENDING = 20;

    public function __construct(private AuditLogger $audit) {}

    public function index(Request $request): JsonResponse
    {
        $supplier = $request->user()->supplier;
        abort_unless($supplier, 403);

        return response()->json([
            'proposals' => ProductProposal::query()
                ->where('supplier_id', $supplier->id)
                ->with(['category:id,name', 'product:id,name'])
                ->latest()
                ->limit(50)
                ->get(),
        ]);
    }

    public function store(Request $request, ProductSearch $search): JsonResponse
    {
        $supplier = $request->user()->activeSupplier();

        if (! $supplier || $supplier->status !== 'approved') {
            abort(403, __('Only approved suppliers can register products.'));
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:120'],
            'name_sw' => ['nullable', 'string', 'max:120'],
            'category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')->where('is_active', true)],
            'unit' => ['required', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        // Point to the catalog entry instead of creating a duplicate.
        $normalized = SearchText::normalize($validated['name']);
        $duplicate = collect($search->index())->first(
            fn ($entry) => $entry['name'] === $normalized
                || in_array($normalized, array_map([SearchText::class, 'normalize'], $entry['labels']), true)
        );

        if ($duplicate) {
            throw ValidationException::withMessages([
                'name' => __('This product is already in the catalog. Pick it from the list instead.'),
            ]);
        }

        $pending = ProductProposal::query()->where('supplier_id', $supplier->id)->where('status', 'pending');

        if ((clone $pending)->count() >= self::MAX_PENDING) {
            throw ValidationException::withMessages([
                'name' => __('You have many products waiting for review. Please wait for those to be checked first.'),
            ]);
        }

        if ((clone $pending)->whereRaw('lower(name) = ?', [mb_strtolower($validated['name'])])->exists()) {
            throw ValidationException::withMessages([
                'name' => __('You already asked for this product. It is waiting for review.'),
            ]);
        }

        $proposal = new ProductProposal($validated);
        $proposal->supplier_id = $supplier->id;
        $proposal->user_id = $request->user()->id;
        $proposal->status = 'pending';
        $proposal->save();

        $this->audit->record('product_proposal.created', "Supplier proposed product \"{$proposal->name}\".", $proposal);

        return response()->json([
            'message' => __('Sent for review. You can add it to your products once it is approved.'),
            'proposal' => $proposal->load('category:id,name'),
        ], 201);
    }
}
