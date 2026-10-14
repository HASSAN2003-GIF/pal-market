<?php

namespace App\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Consistent pagination envelope for list endpoints: { data, meta }.
 */
class Paginated
{
    public static function perPage(Request $request, int $default = 15, int $max = 50): int
    {
        return min(max((int) $request->integer('per_page', $default), 1), $max);
    }

    public static function json(LengthAwarePaginator $paginator, ?callable $transform = null, array $extra = []): JsonResponse
    {
        $items = collect($paginator->items());

        return response()->json([
            'data' => $transform ? $items->map($transform)->values() : $items->values(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
            ...$extra,
        ]);
    }

    /**
     * Escapes LIKE wildcards in user-supplied search terms.
     */
    public static function like(string $term): string
    {
        return '%'.addcslashes(trim($term), '%_\\').'%';
    }
}
