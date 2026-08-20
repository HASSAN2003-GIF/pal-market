<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SupplierLocation;
use App\Services\SupplierLocationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SupplierLocationController extends Controller
{
    public function __construct(
        private SupplierLocationService $supplierLocationService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', SupplierLocation::class);

        $locations = $request->user()
            ->supplier
            ->locations()
            ->latest()
            ->get();

        return response()->json([
            'supplier_locations' => $locations,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', SupplierLocation::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:500'],
            'region' => ['required', 'string', 'max:255'],
            'district' => ['nullable', 'string', 'max:255'],
            'ward' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'phone' => ['nullable', 'string', 'max:50'],
            'is_primary' => ['sometimes', 'boolean'],
        ]);

        try {
            $location = $this->supplierLocationService->create(
                $request->user()->supplier,
                $validated['name'],
                $validated['address'],
                $validated['region'],
                $validated['district'] ?? null,
                $validated['ward'] ?? null,
                isset($validated['latitude'])
                    ? (string) $validated['latitude']
                    : null,
                isset($validated['longitude'])
                    ? (string) $validated['longitude']
                    : null,
                $validated['phone'] ?? null,
                $validated['is_primary'] ?? false
            );

            return response()->json([
                'message' => 'Supplier location created successfully.',
                'supplier_location' => $location,
            ], 201);
        } catch (ValidationException $exception) {
            return response()->json([
                'message' => 'Unable to create supplier location.',
                'errors' => $exception->errors(),
            ], 422);
        }
    }

    public function show(
        SupplierLocation $supplierLocation
    ): JsonResponse {
        $this->authorize('view', $supplierLocation);

        return response()->json([
            'supplier_location' => $supplierLocation,
        ]);
    }

    public function update(
        Request $request,
        SupplierLocation $supplierLocation
    ): JsonResponse {
        $this->authorize('update', $supplierLocation);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:500'],
            'region' => ['required', 'string', 'max:255'],
            'district' => ['nullable', 'string', 'max:255'],
            'ward' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'phone' => ['nullable', 'string', 'max:50'],
            'is_primary' => ['sometimes', 'boolean'],
            'status' => ['sometimes', 'in:active,inactive'],
        ]);

        $location = $this->supplierLocationService->update(
            $supplierLocation,
            $validated['name'],
            $validated['address'],
            $validated['region'],
            $validated['district'] ?? null,
            $validated['ward'] ?? null,
            isset($validated['latitude'])
                ? (string) $validated['latitude']
                : null,
            isset($validated['longitude'])
                ? (string) $validated['longitude']
                : null,
            $validated['phone'] ?? null,
            $validated['is_primary'] ?? null,
            $validated['status'] ?? null
        );

        return response()->json([
            'message' => 'Supplier location updated successfully.',
            'supplier_location' => $location,
        ]);
    }

    public function destroy(
        SupplierLocation $supplierLocation
    ): JsonResponse {
        $this->authorize('delete', $supplierLocation);

        $supplierLocation->delete();

        return response()->json([
            'message' => 'Supplier location removed successfully.',
        ]);
    }
}
