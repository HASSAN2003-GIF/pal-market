<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AccountProvisioner;
use App\Services\AuditLogger;
use App\Support\UserPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

/**
 * Google sign-in.
 *
 * - OAuth "state" is validated through the session (the previous stateless()
 *   flow accepted forged callbacks, enabling login CSRF).
 * - The API token is never placed in a URL. The callback redirects with a
 *   single-use code valid for two minutes, which the SPA exchanges for a token.
 */
class GoogleAuthController extends Controller
{
    private const CODE_TTL_SECONDS = 120;

    public function __construct(
        private AccountProvisioner $provisioner,
        private AuditLogger $audit,
    ) {}

    public function redirect(Request $request): RedirectResponse
    {
        $request->session()->put(
            'google_signup_role',
            $request->query('role') === 'supplier' ? 'supplier' : 'buyer',
        );

        return Socialite::driver('google')->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (Throwable $exception) {
            logger()->warning('Google sign-in callback failed', ['exception' => $exception::class]);

            return $this->fail('Google sign-in could not be completed. Please try again.');
        }

        if (! $googleUser->getEmail() || ! $this->emailVerifiedByGoogle($googleUser)) {
            return $this->fail('Your Google account does not have a verified email address.');
        }

        $email = Str::lower($googleUser->getEmail());

        $user = User::where('google_id', $googleUser->getId())->first()
            ?? User::where('email', $email)->first();

        if ($user) {
            if ($user->isSuspended()) {
                return $this->fail('This account has been suspended. Please contact PAL Market support.');
            }

            if ($user->google_id === null) {
                $this->linkExistingAccount($user, $googleUser);
            } else {
                $user->forceFill(['avatar' => $googleUser->getAvatar()])->save();
            }
        } else {
            $user = $this->provisioner->create(
                [
                    'name' => $googleUser->getName() ?: $googleUser->getNickname() ?: 'PAL Market User',
                    'email' => $email,
                    'google_id' => $googleUser->getId(),
                    'avatar' => $googleUser->getAvatar(),
                    'password' => Str::random(64),
                ],
                (string) $request->session()->pull('google_signup_role', 'buyer'),
            );
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        $this->provisioner->ensureProfile($user);
        $user->forceFill(['last_login_at' => now()])->save();

        $code = Str::random(64);
        Cache::put($this->cacheKey($code), $user->id, self::CODE_TTL_SECONDS);

        return redirect('/auth/google/success?code='.$code);
    }

    public function exchange(Request $request): JsonResponse
    {
        $validated = $request->validate(['code' => ['required', 'string', 'size:64']]);

        $userId = Cache::pull($this->cacheKey($validated['code']));
        $user = $userId ? User::find($userId) : null;

        if (! $user || $user->isSuspended()) {
            throw ValidationException::withMessages([
                'code' => __('This sign-in link has expired. Please sign in with Google again.'),
            ]);
        }

        return response()->json([
            'message' => __('Login successful.'),
            'user' => UserPresenter::present($user),
            'token' => $user->createToken('api-token')->plainTextToken,
        ]);
    }

    /**
     * Google has just proven the person controls this email address.
     *
     * If the existing password account never proved the same, it may have been
     * pre-registered by someone else to hijack the address later. In that case
     * the old password is replaced and every existing session revoked, so only
     * the verified owner keeps access (they can set a password via reset).
     */
    private function linkExistingAccount(User $user, SocialiteUser $googleUser): void
    {
        $wasVerified = $user->isEmailVerified();

        $changes = [
            'google_id' => $googleUser->getId(),
            'avatar' => $googleUser->getAvatar(),
            'email_verified_at' => $user->email_verified_at ?? now(),
        ];

        if (! $wasVerified) {
            $changes['password'] = Str::random(64);
            $user->tokens()->delete();
        }

        $user->forceFill($changes)->save();

        $this->audit->record(
            'account.google_linked',
            $wasVerified
                ? 'Linked Google sign-in to an existing verified account.'
                : 'Linked Google sign-in to an unverified account; previous password and sessions revoked.',
            $user,
            actor: $user,
        );
    }

    private function emailVerifiedByGoogle(SocialiteUser $googleUser): bool
    {
        $raw = method_exists($googleUser, 'getRaw') ? $googleUser->getRaw() : ($googleUser->user ?? []);

        // Google returns email_verified on the userinfo payload; treat a
        // missing flag as unverified.
        return filter_var($raw['email_verified'] ?? $raw['verified_email'] ?? false, FILTER_VALIDATE_BOOL);
    }

    private function cacheKey(string $code): string
    {
        return 'google-login:'.hash('sha256', $code);
    }

    private function fail(string $message): RedirectResponse
    {
        return redirect('/login?error='.urlencode($message));
    }
}
