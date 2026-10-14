<?php

namespace App\Http\Middleware;

use App\Services\TwoFactorService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    public function handle(
        Request $request,
        Closure $next
    ): Response {
        if (! $request->user()?->isAdmin()) {
            abort(403);
        }

        // A stolen admin password alone must not open the console.
        if (TwoFactorService::requiredForAdmins() && ! $request->user()->hasTwoFactorEnabled()) {
            return response()->json([
                'message' => __('Turn on two-step sign-in in your account before using the admin console.'),
                'code' => 'two_factor_setup_required',
            ], 403);
        }

        return $next($request);
    }
}
