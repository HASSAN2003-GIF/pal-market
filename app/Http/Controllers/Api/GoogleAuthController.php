<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends Controller
{
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('google')
            ->stateless()
            ->redirect();
    }

    public function callback()
    {
        $googleUser = Socialite::driver('google')
            ->stateless()
            ->user();

        if (! $googleUser->getEmail()) {
            throw ValidationException::withMessages([
                'google' => 'Google did not provide an email address.',
            ]);
        }

        $user = User::where('google_id', $googleUser->getId())
            ->first();

        if (! $user) {
            $user = User::where('email', $googleUser->getEmail())
                ->first();
        }

        if ($user) {
            if (
                $user->google_id === null &&
                ! $user->isVerified()
            ) {
                throw ValidationException::withMessages([
                    'google' =>
                        'This email already has an unverified PAL Market account. Please verify that account before using Google sign-in.',
                ]);
            }

            $user->forceFill([
                'google_id' => $googleUser->getId(),
                'avatar' => $googleUser->getAvatar(),
                'email_verified_at' => $user->email_verified_at
                    ?? now(),
            ])->save();
        } else {
    $user = User::create([
        'name' => $googleUser->getName()
            ?: $googleUser->getNickname()
            ?: 'PAL Market User',
        'email' => $googleUser->getEmail(),
        'google_id' => $googleUser->getId(),
        'avatar' => $googleUser->getAvatar(),
        'role' => 'buyer',
        'email_verified_at' => now(),
        'password' => \Illuminate\Support\Str::random(64),
    ]);
}

        $token = $user
            ->createToken('api-token')
            ->plainTextToken;

        return redirect(
            '/auth/google/success?token='
            . urlencode($token)
        );
    }
}