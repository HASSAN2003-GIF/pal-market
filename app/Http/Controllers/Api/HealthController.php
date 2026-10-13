<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Readiness probe for load balancers and uptime monitors. Unlike /up (which
 * only proves PHP boots), this verifies the database and cache respond.
 * Returns 503 when a dependency is down. Exposes no configuration details.
 */
class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $checks = [
            'database' => $this->check(fn () => DB::select('select 1')),
            'cache' => $this->check(function () {
                $key = 'health:'.Str::random(8);
                Cache::put($key, 'ok', 10);
                $ok = Cache::pull($key) === 'ok';
                throw_unless($ok, new \RuntimeException('cache round-trip failed'));
            }),
        ];

        $healthy = ! in_array(false, $checks, true);

        return response()->json([
            'status' => $healthy ? 'ok' : 'degraded',
            'checks' => array_map(fn (bool $ok) => $ok ? 'ok' : 'failing', $checks),
            'time' => now()->toIso8601String(),
        ], $healthy ? 200 : 503);
    }

    private function check(callable $probe): bool
    {
        try {
            $probe();

            return true;
        } catch (Throwable $exception) {
            logger()->error('Health check failed', ['exception' => $exception->getMessage()]);

            return false;
        }
    }
}
