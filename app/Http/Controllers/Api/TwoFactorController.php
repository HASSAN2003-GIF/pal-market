<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use App\Services\TwoFactorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Turn two-step sign-in (authenticator app) on and off for the signed-in
 * account. Required for administrators; optional for everyone else.
 */
class TwoFactorController extends Controller
{
    public function __construct(
        private TwoFactorService $twoFactor,
        private AuditLogger $audit,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'enabled' => $user->hasTwoFactorEnabled(),
            'confirmed_at' => $user->two_factor_confirmed_at,
            'recovery_codes_left' => $user->hasTwoFactorEnabled() ? $this->twoFactor->remainingRecoveryCodes($user) : 0,
            'required' => $user->isAdmin() && TwoFactorService::requiredForAdmins(),
        ]);
    }

    /**
     * Step 1: a new secret, shown as a QR code to scan. Not active until a
     * code from the app is confirmed. Needs the account password.
     */
    public function start(Request $request): JsonResponse
    {
        $request->validate(['password' => ['required', 'string', 'current_password:sanctum']]);
        $user = $request->user();

        if ($user->hasTwoFactorEnabled()) {
            throw ValidationException::withMessages(['password' => __('Two-step sign-in is already on.')]);
        }

        $secret = $this->twoFactor->newSecret();
        $user->forceFill(['two_factor_secret' => $secret, 'two_factor_confirmed_at' => null])->save();
        $url = $this->twoFactor->otpauthUrl($user, $secret);

        return response()->json([
            'secret' => $secret,
            'otpauth_url' => $url,
            'qr_svg' => $this->twoFactor->qrSvg($url),
        ]);
    }

    /**
     * Step 2: prove the app works by entering a code. Returns backup codes
     * once, and signs out every other device.
     */
    public function confirm(Request $request): JsonResponse
    {
        $validated = $request->validate(['code' => ['required', 'string', 'max:20']]);
        $user = $request->user();

        if (! $user->two_factor_secret || $user->hasTwoFactorEnabled()) {
            throw ValidationException::withMessages(['code' => __('Start setting up two-step sign-in first.')]);
        }

        if (! $this->twoFactor->verifyCode($user, $user->two_factor_secret, $validated['code'])) {
            throw ValidationException::withMessages(['code' => __('That code is not correct. Check the time on your phone and try again.')]);
        }

        $user->forceFill(['two_factor_confirmed_at' => now()])->save();
        $codes = $this->twoFactor->newRecoveryCodes($user);
        $user->tokens()->whereKeyNot($user->currentAccessToken()->getKey())->delete();

        $this->audit->record('account.two_factor_enabled', 'Turned on two-step sign-in and signed out other devices.', $user);

        return response()->json([
            'message' => __('Two-step sign-in is on.'),
            'recovery_codes' => $codes,
        ]);
    }

    public function regenerate(Request $request): JsonResponse
    {
        $request->validate(['password' => ['required', 'string', 'current_password:sanctum']]);
        $user = $request->user();

        if (! $user->hasTwoFactorEnabled()) {
            throw ValidationException::withMessages(['password' => __('Two-step sign-in is not on.')]);
        }

        $codes = $this->twoFactor->newRecoveryCodes($user);
        $this->audit->record('account.two_factor_recovery_regenerated', 'Replaced two-step backup codes.', $user);

        return response()->json(['recovery_codes' => $codes]);
    }

    /**
     * Turning it off needs the password and a current code, so a borrowed
     * unlocked session cannot quietly remove it.
     */
    public function disable(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'string', 'current_password:sanctum'],
            'code' => ['required', 'string', 'max:20'],
        ]);
        $user = $request->user();

        if ($user->hasTwoFactorEnabled() && ! $this->twoFactor->verifyCode($user, $user->two_factor_secret, $validated['code'])) {
            throw ValidationException::withMessages(['code' => __('That code is not correct. Check the time on your phone and try again.')]);
        }

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        $this->audit->record('account.two_factor_disabled', 'Turned off two-step sign-in.', $user);

        return response()->json(['message' => __('Two-step sign-in is off.')]);
    }
}
