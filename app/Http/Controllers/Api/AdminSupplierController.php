<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;

class AdminSupplierController extends Controller
{
    public function index(): JsonResponse
    {
        $suppliers = Supplier::query()
            ->with([
                'user:id,name,email',
            ])
            ->select([
                'id',
                'user_id',
                'business_name',
                'tin_number',
                'status',
                'verified_at',
            ])
            ->orderBy('id')
            ->get();

        return response()->json([
            'suppliers' => $suppliers,
        ]);
    }

    public function show(Supplier $supplier): JsonResponse
    {
        $supplier->load([
            'user:id,name,email',
        ]);

        return response()->json([
            'supplier' => $supplier->only([
                'id',
                'user_id',
                'business_name',
                'tin_number',
                'description',
                'status',
                'verified_at',
                'user',
            ]),
        ]);
    }

    public function approve(Supplier $supplier): JsonResponse
    {
        if ($supplier->status !== 'pending') {
            return response()->json([
                'message' => 'Only pending suppliers can be approved.',
            ], 422);
        }

        $supplier->update([
            'status' => 'approved',
            'verified_at' => now(),
        ]);

        $supplier->load([
            'user:id,name,email',
        ]);

        return response()->json([
            'message' => 'Supplier approved successfully.',
            'supplier' => $supplier->only([
                'id',
                'user_id',
                'business_name',
                'tin_number',
                'description',
                'status',
                'verified_at',
                'user',
            ]),
        ]);
    }

    public function suspend(Supplier $supplier): JsonResponse
    {
        if ($supplier->status !== 'approved') {
            return response()->json([
                'message' => 'Only approved suppliers can be suspended.',
            ], 422);
        }

        $supplier->update([
            'status' => 'suspended',
        ]);

        $supplier->load([
            'user:id,name,email',
        ]);

        return response()->json([
            'message' => 'Supplier suspended successfully.',
            'supplier' => $supplier->only([
                'id',
                'user_id',
                'business_name',
                'tin_number',
                'description',
                'status',
                'verified_at',
                'user',
            ]),
        ]);
    }

    public function reactivate(Supplier $supplier): JsonResponse
    {
        if ($supplier->status !== 'suspended') {
            return response()->json([
                'message' => 'Only suspended suppliers can be reactivated.',
            ], 422);
        }

        $supplier->update([
            'status' => 'approved',
        ]);

        $supplier->load([
            'user:id,name,email',
        ]);

        return response()->json([
            'message' => 'Supplier reactivated successfully.',
            'supplier' => $supplier->only([
                'id',
                'user_id',
                'business_name',
                'tin_number',
                'description',
                'status',
                'verified_at',
                'user',
            ]),
        ]);
    }
}
