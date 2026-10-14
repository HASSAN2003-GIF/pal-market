<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AccountProvisioner;
use App\Services\AuditLogger;
use App\Services\TwoFactorService;
use App\Support\UserPresenter;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    private const MAX_LOGIN_ATTEMPTS = 5;

    public function __construct(
        private AccountProvisioner $provisioner,
        private AuditLogger $audit,
        private TwoFactorService $twoFactor,
    ) {}

    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email:rfc', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^\+?[0-9 ]{9,20}$/', 'unique:users,phone'],
            'password' => ['required', 'string', 'confirmed', PasswordRule::min(8)->letters()->numbers()],
            // Only self-service roles are honoured; anything else (including
            // "admin") falls back to buyer. See AccountProvisioner::create().
            'role' => ['nullable', 'string'],
            'business_name' => ['nullable', 'string', 'max:255'],
            'tin_number' => ['nullable', 'string', 'max:50', 'unique:suppliers,tin_number'],
        ]);

        $user = $this->provisioner->create(
            [
                'name' => trim($validated['first_name'].' '.$validated['last_name']),
                'email' => Str::lower($validated['email']),
                'phone' => $this->normalisePhone($validated['phone'] ?? null),
                'password' => $validated['password'],
            ],
            (string) ($validated['role'] ?? 'buyer'),
            $validated,
        );

        $user->forceFill(['last_login_at' => now()])->save();

        return response()->json([
            'message' => __('Account created successfully.'),
            'user' => UserPresenter::present($user),
            'token' => $this->issueToken($user),
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'login' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
        ]);

        $throttleKey = 'login:'.Str::lower($validated['login']).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_LOGIN_ATTEMPTS)) {
            throw ValidationException::withMessages([
                'login' => __('Too many sign-in attempts. Please try again in :seconds seconds.', ['seconds' => RateLimiter::availableIn($throttleKey)]),
            ])->status(429);
        }

        $login = trim($validated['login']);
        $user = str_contains($login, '@')
            ? User::where('email', Str::lower($login))->first()
            // Phones stored before normalisation may still contain spaces.
            : User::whereIn('phone', array_unique([$login, $this->normalisePhone($login)]))->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            RateLimiter::hit($throttleKey, 60);
            logger()->notice('Failed sign-in attempt', ['ip' => $request->ip()]);

            throw ValidationException::withMessages([
                'login' => __('The provided credentials are incorrect.'),
            ]);
        }

        RateLimiter::clear($throttleKey);

        // Checked only after the password, so suspension status is never
        // revealed to someone who does not know the credentials.
        if ($user->isSuspended()) {
            return response()->json([
                'message' => __('This account has been suspended. Please contact PAL Market support.'),
            ], 403);
        }

        // Password was right; with two-step sign-in a code is still needed.
        if ($user->hasTwoFactorEnabled()) {
            return response()->json([
                'two_factor_required' => true,
                'challenge' => $this->twoFactor->createChallenge($user),
                'message' => __('Enter the 6-digit code from your authenticator app.'),
            ]);
        }

        return $this->completeLogin($user);
    }

    /**
     * Second step of sign-in: a code from the authenticator app, or one of
     * the backup codes.
     */
    public function twoFactorLogin(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'challenge' => ['required', 'string', 'size:64'],
            'code' => ['nullable', 'string', 'max:20', 'required_without:recovery_code'],
            'recovery_code' => ['nullable', 'string', 'max:30'],
        ]);

        $user = $this->twoFactor->challengeUser($validated['challenge']);

        if (! $user || ! $user->hasTwoFactorEnabled()) {
            throw ValidationException::withMessages([
                'code' => __('This sign-in has expired. Please sign in again.'),
            ]);
        }

        $usedRecovery = ! empty($validated['recovery_code']);
        $valid = $usedRecovery
            ? $this->twoFactor->useRecoveryCode($user, $validated['recovery_code'])
            : $this->twoFactor->verifyCode($user, $user->two_factor_secret, (string) $validated['code']);

        if (! $valid) {
            $this->twoFactor->failChallenge($validated['challenge']);
            logger()->notice('Failed two-step sign-in code', ['user_id' => $user->id, 'ip' => $request->ip()]);

            throw ValidationException::withMessages([
                $usedRecovery ? 'recovery_code' : 'code' => __('That code is not correct. Check the time on your phone and try again.'),
            ]);
        }

        $this->twoFactor->clearChallenge($validated['challenge']);

        if ($user->isSuspended()) {
            return response()->json([
                'message' => __('This account has been suspended. Please contact PAL Market support.'),
            ], 403);
        }

        if ($usedRecovery) {
            $this->audit->record('account.two_factor_recovery_used', 'Signed in with a backup code ('.$this->twoFactor->remainingRecoveryCodes($user).' left).', $user, actor: $user);
        }

        return $this->completeLogin($user);
    }

    private function completeLogin(User $user): JsonResponse
    {
        $this->provisioner->ensureProfile($user);
        $user->forceFill(['last_login_at' => now()])->save();

        if ($user->isAdmin()) {
            $this->audit->record('admin.signed_in', 'Administrator signed in.', $user, actor: $user);
        }

        return response()->json([
            'message' => __('Login successful.'),
            'user' => UserPresenter::present($user),
            'token' => $this->issueToken($user),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => UserPresenter::present($request->user()),
        ]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^\+?[0-9 ]{9,20}$/', Rule::unique('users', 'phone')->ignore($user->id)],
            'business_name' => ['sometimes', 'required', 'string', 'max:255'],
            'business_type' => ['sometimes', 'nullable', 'string', 'max:100'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'tin_number' => [
                'sometimes', 'nullable', 'string', 'max:50',
                Rule::unique('suppliers', 'tin_number')->ignore($user->supplier?->id),
            ],
        ]);

        $user->fill([
            'name' => $validated['name'],
            'phone' => $this->normalisePhone($validated['phone'] ?? null),
        ])->save();

        $business = collect($validated)->only(['business_name', 'business_type', 'description', 'tin_number']);

        if ($user->buyerProfile && $business->isNotEmpty()) {
            $user->buyerProfile->update($business->all());
        }

        if ($user->supplier && $business->isNotEmpty()) {
            // An approved supplier's TIN was verified by an administrator, so
            // changing it is not self-service.
            $supplierChanges = $business->except('business_type');
            if ($user->supplier->status === 'approved') {
                $supplierChanges = $supplierChanges->except('tin_number');
            }
            $user->supplier->update($supplierChanges->all());
        }

        return response()->json([
            'message' => __('Profile updated.'),
            'user' => UserPresenter::present($user->fresh()),
        ]);
    }

    public function updatePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string', 'current_password:sanctum'],
            'password' => ['required', 'string', 'confirmed', PasswordRule::min(8)->letters()->numbers()],
        ]);

        $user = $request->user();
        $user->forceFill(['password' => $validated['password']])->save();

        // Sign out every other device.
        $user->tokens()->whereKeyNot($user->currentAccessToken()->getKey())->delete();

        $this->audit->record('account.password_changed', 'Changed account password and signed out other devices.', $user);

        return response()->json(['message' => __('Password updated. Other devices have been signed out.')]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json([
            'message' => __('Logout successful.'),
        ]);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $validated = $request->validate(['email' => ['required', 'email', 'max:255']]);

        // The response is identical whether or not the account exists, to
        // avoid revealing which email addresses are registered.
        Password::sendResetLink(['email' => Str::lower($validated['email'])]);

        return response()->json([
            'message' => __('If an account exists for that email, a password reset link has been sent.'),
        ]);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'confirmed', PasswordRule::min(8)->letters()->numbers()],
        ]);

        $status = Password::reset(
            [...$validated, 'email' => Str::lower($validated['email'])],
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                    // Receiving the reset email proves control of the address.
                    'email_verified_at' => $user->email_verified_at ?? now(),
                ])->save();

                $user->tokens()->delete();
                event(new PasswordReset($user));
                $this->audit->record('account.password_reset', 'Reset password by email and signed out all devices.', $user, actor: $user);
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => __($status)]);
        }

        return response()->json(['message' => __('Your password has been reset. You can now sign in.')]);
    }

    private function issueToken(User $user): string
    {
        return $user->createToken('api-token')->plainTextToken;
    }

    private function normalisePhone(?string $phone): ?string
    {
        if ($phone === null || trim($phone) === '') {
            return null;
        }

        return preg_replace('/\s+/', '', trim($phone));
    }
}
