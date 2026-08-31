<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30', 'unique:users,phone'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role' => ['required', 'string', 'in:buyer,supplier'], // We explicitly require a valid role
        ]);

        $user = \App\Models\User::create([
            'name' => trim($validated['first_name'] . ' ' . $validated['last_name']),
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'password' => \Illuminate\Support\Facades\Hash::make($validated['password']), // Secure hashing restored
            'role' => $validated['role'], // Role is now dynamic
            'email_verified_at' => now(),
        ]);

        // Automatically generate a profile for the user based on their role
        // Automatically generate a profile for the user based on their role
        if ($validated['role'] === 'buyer') {
            // Provide a default business name to satisfy the database constraint
            $user->buyerProfile()->create([
                'business_name' => $user->name, 
            ]);
        } else {
            // Suppliers need an entry in the suppliers table
            $user->supplier()->create([
                'company_name' => $user->name . ' Hardware', // Default name they can change later
                'status' => 'pending', // Keeps them from selling until admin approves
            ]);
        }

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'message' => 'Account created successfully.',
            'user' => $user,
            'token' => $token,
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $user = User::query()
            ->where('email', $validated['login'])
            ->orWhere('phone', $validated['login'])
            ->first();

        if (
            ! $user ||
            ! Hash::check($validated['password'], $user->password)
        ) {
            throw ValidationException::withMessages([
                'login' => 'The provided credentials are incorrect.',
            ]);
        }

        $token = $user
            ->createToken('api-token')
            ->plainTextToken;

        return response()->json([
            'message' => 'Login successful.',
            'user' => $user,
            'token' => $token,
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $request->user(),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json([
            'message' => 'Logout successful.',
        ]);
    }
}