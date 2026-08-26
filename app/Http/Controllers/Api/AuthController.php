<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\VerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(
        Request $request,
        VerificationService $verificationService
    ): JsonResponse {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30', 'unique:users,phone'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'verification_channel' => [
                'required',
                'in:email,phone',
            ],
        ]);

        if (
            $validated['verification_channel'] === 'phone' &&
            empty($validated['phone'])
        ) {
            throw ValidationException::withMessages([
                'phone' =>
                    'A phone number is required for phone verification.',
            ]);
        }

        $user = User::create([
            'name' => trim(
                $validated['first_name'] . ' ' . $validated['last_name']
            ),
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'password' => $validated['password'],
            'role' => 'buyer',
        ]);

        $verificationService->send(
            $user,
            $validated['verification_channel']
        );

        return response()->json([
            'message' =>
                'Account created. Please verify your account.',
            'user' => $user,
            'verification' => [
                'channel' => $validated['verification_channel'],
                'destination' => $this->maskDestination(
                    $validated['verification_channel'],
                    $validated['verification_channel'] === 'email'
                        ? $user->email
                        : $user->phone
                ),
            ],
        ], 201);
    }

    public function verify(
        Request $request,
        VerificationService $verificationService
    ): JsonResponse {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'channel' => ['required', 'in:email,phone'],
            'code' => ['required', 'digits:6'],
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'email' => 'Unable to verify this account.',
            ]);
        }

        $verificationService->verify(
            $user,
            $validated['channel'],
            $validated['code']
        );

        $token = $user
            ->createToken('api-token')
            ->plainTextToken;

        return response()->json([
            'message' => 'Account verified successfully.',
            'user' => $user->fresh(),
            'token' => $token,
        ]);
    }

    public function resendVerification(
        Request $request,
        VerificationService $verificationService
    ): JsonResponse {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'channel' => ['required', 'in:email,phone'],
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'email' => 'Unable to resend verification code.',
            ]);
        }

        if (
            $validated['channel'] === 'email' &&
            $user->email_verified_at
        ) {
            throw ValidationException::withMessages([
                'email' => 'This email address is already verified.',
            ]);
        }

        if (
            $validated['channel'] === 'phone' &&
            $user->phone_verified_at
        ) {
            throw ValidationException::withMessages([
                'phone' => 'This phone number is already verified.',
            ]);
        }

        $verificationService->send(
            $user,
            $validated['channel']
        );

        return response()->json([
            'message' => 'A new verification code has been sent.',
        ]);
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

        if (! $user->isVerified()) {
            throw ValidationException::withMessages([
                'verification' =>
                    'Please verify your account before signing in.',
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

    private function maskDestination(
        string $channel,
        string $destination
    ): string {
        if ($channel === 'email') {
            [$name, $domain] = explode('@', $destination, 2);

            $visible = substr($name, 0, 1);

            return $visible
                . str_repeat('*', max(strlen($name) - 1, 2))
                . '@'
                . $domain;
        }

        $length = strlen($destination);

        if ($length <= 4) {
            return str_repeat('*', $length);
        }

        return str_repeat('*', $length - 4)
            . substr($destination, -4);
    }
}