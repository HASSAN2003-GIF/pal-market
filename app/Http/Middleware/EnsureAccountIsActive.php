<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rejects every authenticated request from a suspended account, so a
 * suspension takes effect immediately even for tokens issued earlier.
 */
class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->isSuspended()) {
            return response()->json([
                'message' => __('This account has been suspended. Please contact PAL Market support.'),
                'code' => 'account_suspended',
            ], 403);
        }

        return $next($request);
    }
}
