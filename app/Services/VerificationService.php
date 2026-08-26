<?php

namespace App\Services;

use App\Models\User;
use App\Models\VerificationCode;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class VerificationService
{
    private const CODE_LENGTH = 6;

    private const CODE_EXPIRATION_MINUTES = 10;

    private const MAX_ATTEMPTS = 5;

    private const RESEND_COOLDOWN_SECONDS = 60;

    public function send(User $user, string $channel): VerificationCode
    {
        if (! in_array($channel, ['email', 'phone'], true)) {
            throw ValidationException::withMessages([
                'channel' => 'The verification channel must be email or phone.',
            ]);
        }

        $destination = $this->destinationFor($user, $channel);

        if (! $destination) {
            throw ValidationException::withMessages([
                $channel => "No {$channel} is available for this account.",
            ]);
        }

        $recentCode = VerificationCode::query()
            ->where('user_id', $user->id)
            ->where('channel', $channel)
            ->latest()
            ->first();

        if (
            $recentCode &&
            $recentCode->created_at->diffInSeconds(now()) <
            self::RESEND_COOLDOWN_SECONDS
        ) {
            throw ValidationException::withMessages([
                'verification' =>
                    'Please wait before requesting another verification code.',
            ]);
        }

        VerificationCode::query()
            ->where('user_id', $user->id)
            ->where('channel', $channel)
            ->whereNull('verified_at')
            ->update([
                'expires_at' => now(),
            ]);

        $plainCode = str_pad(
            (string) random_int(0, 999999),
            self::CODE_LENGTH,
            '0',
            STR_PAD_LEFT
        );

        $verificationCode = VerificationCode::create([
            'user_id' => $user->id,
            'channel' => $channel,
            'destination' => $destination,
            'code' => Hash::make($plainCode),
            'expires_at' => now()->addMinutes(
                self::CODE_EXPIRATION_MINUTES
            ),
        ]);

        $this->deliver($user, $channel, $destination, $plainCode);

        return $verificationCode;
    }

    public function verify(
        User $user,
        string $channel,
        string $code
    ): void {
        $verification = VerificationCode::query()
            ->where('user_id', $user->id)
            ->where('channel', $channel)
            ->whereNull('verified_at')
            ->latest()
            ->first();

        if (! $verification) {
            throw ValidationException::withMessages([
                'code' => 'No active verification code was found.',
            ]);
        }

        if ($verification->isExpired()) {
            throw ValidationException::withMessages([
                'code' => 'This verification code has expired.',
            ]);
        }

        if ($verification->attempts >= self::MAX_ATTEMPTS) {
            throw ValidationException::withMessages([
                'code' =>
                    'Too many incorrect attempts. Please request a new code.',
            ]);
        }

        if (! Hash::check($code, $verification->code)) {
            $verification->incrementAttempts();

            throw ValidationException::withMessages([
                'code' => 'The verification code is incorrect.',
            ]);
        }

        $verification->markVerified();

        if ($channel === 'email') {
            $user->forceFill([
                'email_verified_at' => now(),
            ])->save();
        }

        if ($channel === 'phone') {
            $user->forceFill([
                'phone_verified_at' => now(),
            ])->save();
        }
    }

    private function destinationFor(
        User $user,
        string $channel
    ): ?string {
        return match ($channel) {
            'email' => $user->email,
            'phone' => $user->phone,
            default => null,
        };
    }

    private function deliver(
        User $user,
        string $channel,
        string $destination,
        string $code
    ): void {
        if ($channel === 'email') {
            Mail::raw(
                "Your PAL Market verification code is {$code}. "
                . 'This code expires in 10 minutes.',
                function ($message) use ($destination) {
                    $message
                        ->to($destination)
                        ->subject('PAL Market verification code');
                }
            );

            return;
        }

        /*
         * SMS delivery will be connected to a real SMS provider later.
         *
         * For local development we log the code instead of sending
         * an actual SMS.
         */
        logger()->info('PAL Market phone verification code', [
            'user_id' => $user->id,
            'phone' => $destination,
            'code' => $code,
        ]);
    }
}