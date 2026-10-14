<?php

namespace App\Services;

use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

/**
 * Authenticator-app codes (TOTP, RFC 6238) and single-use backup codes.
 *
 * - A code is accepted for its 30-second step and one step either side
 *   (clock drift), and each step can be used only once per account.
 * - Backup codes are shown once, stored hashed, and removed when used.
 * - Sign-in with two-step is split in two calls: the password call returns a
 *   short-lived challenge instead of a token; the code call exchanges it.
 */
class TwoFactorService
{
    private const CHALLENGE_TTL_SECONDS = 300;

    private const CHALLENGE_MAX_ATTEMPTS = 5;

    private const RECOVERY_CODES = 8;

    public function __construct(private Google2FA $google2fa) {}

    public static function requiredForAdmins(): bool
    {
        return (bool) config('auth.admin_two_factor', true);
    }

    public function newSecret(): string
    {
        return $this->google2fa->generateSecretKey(32);
    }

    public function otpauthUrl(User $user, string $secret): string
    {
        return $this->google2fa->getQRCodeUrl(config('app.name', 'PAL Market'), $user->email, $secret);
    }

    public function qrSvg(string $otpauthUrl): string
    {
        $writer = new Writer(new ImageRenderer(new RendererStyle(220, 1), new SvgImageBackEnd));

        return $writer->writeString($otpauthUrl);
    }

    /**
     * Checks a 6-digit code against a secret, refusing a step already used.
     */
    public function verifyCode(User $user, string $secret, string $code): bool
    {
        $code = preg_replace('/\s+/', '', $code);

        if (! preg_match('/^\d{6}$/', $code)) {
            return false;
        }

        $cacheKey = 'two-factor-last-step:'.$user->id;
        $lastStep = (int) Cache::get($cacheKey, 0);

        // With a previous step given, returns the matching (newer) timestep
        // as an int, or false. Steps at or before $lastStep never match.
        $step = $this->google2fa->verifyKeyNewer($secret, $code, $lastStep, 1);

        if (! is_int($step)) {
            return false;
        }

        Cache::put($cacheKey, $step, now()->addMinutes(5));

        return true;
    }

    /**
     * @return list<string> plain codes, shown to the person once
     */
    public function newRecoveryCodes(User $user): array
    {
        $codes = Collection::times(self::RECOVERY_CODES, fn () => Str::lower(Str::random(5).'-'.Str::random(5)))->all();

        $user->forceFill([
            'two_factor_recovery_codes' => array_map(fn ($code) => Hash::make($code), $codes),
        ])->save();

        return $codes;
    }

    public function useRecoveryCode(User $user, string $code): bool
    {
        $code = Str::lower(trim($code));
        $hashes = $user->two_factor_recovery_codes ?? [];

        foreach ($hashes as $index => $hash) {
            if (Hash::check($code, $hash)) {
                unset($hashes[$index]);
                $user->forceFill(['two_factor_recovery_codes' => array_values($hashes)])->save();

                return true;
            }
        }

        return false;
    }

    public function remainingRecoveryCodes(User $user): int
    {
        return count($user->two_factor_recovery_codes ?? []);
    }

    public function createChallenge(User $user): string
    {
        $challenge = Str::random(64);
        Cache::put($this->challengeKey($challenge), ['user_id' => $user->id, 'attempts' => 0], self::CHALLENGE_TTL_SECONDS);

        return $challenge;
    }

    /**
     * Resolves a challenge to its user, counting failed attempts. Returns
     * null when the challenge is unknown, expired or used up.
     */
    public function challengeUser(string $challenge): ?User
    {
        $data = Cache::get($this->challengeKey($challenge));

        if (! $data || $data['attempts'] >= self::CHALLENGE_MAX_ATTEMPTS) {
            return null;
        }

        return User::find($data['user_id']);
    }

    public function failChallenge(string $challenge): void
    {
        $key = $this->challengeKey($challenge);
        $data = Cache::get($key);

        if ($data) {
            $data['attempts']++;
            Cache::put($key, $data, self::CHALLENGE_TTL_SECONDS);
        }
    }

    public function clearChallenge(string $challenge): void
    {
        Cache::forget($this->challengeKey($challenge));
    }

    private function challengeKey(string $challenge): string
    {
        return 'two-factor-challenge:'.hash('sha256', $challenge);
    }
}
