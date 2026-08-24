<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BuyerProfile;
use Illuminate\Http\JsonResponse;

class AdminBuyerController extends Controller
{
    public function index(): JsonResponse
    {
        $buyers = BuyerProfile::query()
            ->with([
                'user:id,name,email',
            ])
            ->orderBy('id')
            ->get();

        return response()->json([
            'buyers' => $buyers,
        ]);
    }

    public function show(BuyerProfile $buyer): JsonResponse
    {
        $buyer->load([
            'user:id,name,email',
        ]);

        return response()->json([
            'buyer' => $buyer,
        ]);
    }

    public function suspend(BuyerProfile $buyer): JsonResponse
    {
        if ($buyer->status !== 'active') {
            return response()->json([
                'message' => 'Only active buyers can be suspended.',
            ], 422);
        }

        $buyer->update([
            'status' => 'suspended',
        ]);

        $buyer->load([
            'user:id,name,email',
        ]);

        return response()->json([
            'message' => 'Buyer suspended successfully.',
            'buyer' => $buyer,
        ]);
    }

    public function reactivate(BuyerProfile $buyer): JsonResponse
    {
        if ($buyer->status !== 'suspended') {
            return response()->json([
                'message' => 'Only suspended buyers can be reactivated.',
            ], 422);
        }

        $buyer->update([
            'status' => 'active',
        ]);

        $buyer->load([
            'user:id,name,email',
        ]);

        return response()->json([
            'message' => 'Buyer reactivated successfully.',
            'buyer' => $buyer,
        ]);
    }
}
