<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Services\AuditLogger;
use App\Support\Paginated;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminSupplierController extends Controller
{
    private const FIELDS = ['id', 'user_id', 'business_name', 'tin_number', 'description', 'status', 'verified_at', 'created_at', 'user'];

    public function __construct(private AuditLogger $audit) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:pending,approved,suspended'],
        ]);

        $suppliers = Supplier::query()
            ->with('user:id,name,email,phone')
            ->withCount(['locations', 'supplierProducts', 'quotations', 'purchaseOrders'])
            ->when($validated['search'] ?? null, fn (Builder $q, $term) => $q->where(fn (Builder $w) => $w
                ->where('business_name', 'like', Paginated::like($term))
                ->orWhere('tin_number', 'like', Paginated::like($term))
                ->orWhereHas('user', fn (Builder $u) => $u->where('email', 'like', Paginated::like($term)))))
            ->when($validated['status'] ?? null, fn (Builder $q, $status) => $q->where('status', $status))
            ->orderBy('id')
            ->paginate(Paginated::perPage($request, 25, 100));

        return response()->json([
            'suppliers' => $suppliers->items(),
            'meta' => [
                'current_page' => $suppliers->currentPage(),
                'last_page' => $suppliers->lastPage(),
                'per_page' => $suppliers->perPage(),
                'total' => $suppliers->total(),
            ],
        ]);
    }

    public function show(Supplier $supplier): JsonResponse
    {
        $supplier->load(['user:id,name,email,phone', 'locations'])
            ->loadCount(['supplierProducts', 'quotations', 'purchaseOrders']);

        return response()->json([
            'supplier' => [
                ...$supplier->only(self::FIELDS),
                'locations' => $supplier->locations,
                'supplier_products_count' => $supplier->supplier_products_count,
                'quotations_count' => $supplier->quotations_count,
                'purchase_orders_count' => $supplier->purchase_orders_count,
            ],
        ]);
    }

    public function approve(Supplier $supplier): JsonResponse
    {
        return $this->transition($supplier, 'pending', ['status' => 'approved', 'verified_at' => now()],
            'Only pending suppliers can be approved.', 'Supplier approved successfully.', 'supplier.approved');
    }

    public function suspend(Supplier $supplier): JsonResponse
    {
        return $this->transition($supplier, 'approved', ['status' => 'suspended'],
            'Only approved suppliers can be suspended.', 'Supplier suspended successfully.', 'supplier.suspended');
    }

    public function reactivate(Supplier $supplier): JsonResponse
    {
        return $this->transition($supplier, 'suspended', ['status' => 'approved'],
            'Only suspended suppliers can be reactivated.', 'Supplier reactivated successfully.', 'supplier.reactivated');
    }

    private function transition(
        Supplier $supplier,
        string $requiredStatus,
        array $changes,
        string $invalidMessage,
        string $successMessage,
        string $auditAction,
    ): JsonResponse {
        if ($supplier->status !== $requiredStatus) {
            return response()->json(['message' => $invalidMessage], 422);
        }

        $supplier->update($changes);
        $supplier->load('user:id,name,email');

        $this->audit->record($auditAction, "{$successMessage} ({$supplier->business_name})", $supplier);

        return response()->json([
            'message' => $successMessage,
            'supplier' => $supplier->only(self::FIELDS),
        ]);
    }
}
