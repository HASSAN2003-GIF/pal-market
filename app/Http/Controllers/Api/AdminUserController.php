<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminUserController extends Controller
{
    public function index(): JsonResponse
    {
        $users = User::query()
            ->select([
                'id',
                'name',
                'email',
                'role',
            ])
            ->orderBy('id')
            ->get();

        return response()->json([
            'users' => $users,
        ]);
    }

    public function show(User $user): JsonResponse
    {
        return response()->json([
            'user' => $user->only([
                'id',
                'name',
                'email',
                'role',
            ]),
        ]);
    }

    public function updateRole(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'role' => [
                'required',
                Rule::in([
                    'admin',
                    'buyer',
                    'supplier',
                ]),
            ],
        ]);

        $user->update([
            'role' => $validated['role'],
        ]);

        return response()->json([
            'user' => $user->only([
                'id',
                'name',
                'email',
                'role',
            ]),
        ]);
    }

    public function destroy(User $user): JsonResponse
    {
        $user->delete();

        return response()->json([
            'message' => 'User deleted successfully.',
        ]);
    }
}
