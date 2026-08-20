<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BuyerRequest;
use App\Services\BuyerRequestService;
use App\Services\BuyerRequestStatusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class BuyerRequestController extends Controller
{
    public function __construct(
        private BuyerRequestService $buyerRequestService,
        private BuyerRequestStatusService $buyerRequestStatusService
    ) {}

    public function index(): JsonResponse
    {
        $this->authorize('viewAny', BuyerRequest::class);

        $buyerProfile = request()->user()->buyerProfile;

        $buyerRequests = $buyerProfile
            ->requests()
            ->with('items.product')
            ->latest()
            ->get();

        return response()->json([
            'buyer_requests' => $buyerRequests,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', BuyerRequest::class);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        $buyerRequest = $this->buyerRequestService->create(
            request()->user()->buyerProfile->id,
            $validated['title'],
            $validated['description'] ?? null
        );

        return response()->json([
            'message' => 'Buyer request created successfully.',
            'buyer_request' => $buyerRequest->load([
                'buyerProfile',
                'items.product',
            ]),
        ], 201);
    }

    public function show(
        BuyerRequest $buyerRequest
    ): JsonResponse {
        $this->authorize('view', $buyerRequest);

        return response()->json([
            'buyer_request' => $buyerRequest->load([
                'buyerProfile',
                'items.product',
                'quotations',
            ]),
        ]);
    }

    public function update(
        Request $request,
        BuyerRequest $buyerRequest
    ): JsonResponse {
        $this->authorize('update', $buyerRequest);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        try {
            $buyerRequest = $this->buyerRequestService->update(
                $buyerRequest,
                $validated['title'],
                $validated['description'] ?? null
            );

            return response()->json([
                'message' => 'Buyer request updated successfully.',
                'buyer_request' => $buyerRequest->load([
                    'buyerProfile',
                    'items.product',
                ]),
            ]);
        } catch (ValidationException $exception) {
            return response()->json([
                'message' => 'Unable to update buyer request.',
                'errors' => $exception->errors(),
            ], 422);
        }
    }

    public function addItem(
        Request $request,
        BuyerRequest $buyerRequest
    ): JsonResponse {
        $this->authorize('update', $buyerRequest);

        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'unit' => ['required', 'string', 'max:50'],
            'notes' => ['nullable', 'string'],
        ]);

        try {
            $item = $this->buyerRequestService->addItem(
                $buyerRequest,
                $validated['product_id'],
                $validated['quantity'],
                $validated['unit'],
                $validated['notes'] ?? null
            );

            return response()->json([
                'message' => 'Buyer request item added successfully.',
                'item' => $item->load('product'),
                'buyer_request' => $buyerRequest->fresh()->load('items.product'),
            ], 201);
        } catch (ValidationException $exception) {
            return response()->json([
                'message' => 'Unable to add buyer request item.',
                'errors' => $exception->errors(),
            ], 422);
        }
    }

    public function publish(
        BuyerRequest $buyerRequest
    ): JsonResponse {
        $this->authorize('update', $buyerRequest);

        try {
            $buyerRequest = $this->buyerRequestStatusService
                ->publish($buyerRequest);

            return response()->json([
                'message' => 'Buyer request published successfully.',
                'buyer_request' => $buyerRequest->load([
                    'buyerProfile',
                    'items.product',
                ]),
            ]);
        } catch (ValidationException $exception) {
            return response()->json([
                'message' => 'Unable to publish buyer request.',
                'errors' => $exception->errors(),
            ], 422);
        }
    }

    public function cancel(
        BuyerRequest $buyerRequest
    ): JsonResponse {
        $this->authorize('cancel', $buyerRequest);

        try {
            $buyerRequest = $this->buyerRequestStatusService
                ->cancel($buyerRequest);

            return response()->json([
                'message' => 'Buyer request cancelled successfully.',
                'buyer_request' => $buyerRequest->load([
                    'buyerProfile',
                    'items.product',
                ]),
            ]);
        } catch (ValidationException $exception) {
            return response()->json([
                'message' => 'Unable to cancel buyer request.',
                'errors' => $exception->errors(),
            ], 422);
        }
    }
}