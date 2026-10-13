<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Chooses the language for API messages, validation errors and pages.
 *
 * Order: the language the person picked in the SPA (`X-Locale`), then the
 * signed-in person's saved language, then the browser's Accept-Language,
 * then the app default. Only supported locales are ever applied.
 */
class SetLocale
{
    public const SUPPORTED = ['en', 'sw'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->supported($request->header('X-Locale'))
            ?? $this->supported($request->user('sanctum')?->locale)
            ?? $this->fromAcceptLanguage($request)
            ?? config('app.locale');

        app()->setLocale($locale);

        $response = $next($request);
        $response->headers->set('Content-Language', $locale);

        return $response;
    }

    private function fromAcceptLanguage(Request $request): ?string
    {
        if (! $request->headers->has('Accept-Language')) {
            return null;
        }

        // First supported language in the client's preference order.
        foreach ($request->getLanguages() as $language) {
            if ($supported = $this->supported($language)) {
                return $supported;
            }
        }

        return null;
    }

    private function supported(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $base = strtolower(substr(str_replace('_', '-', $value), 0, 2));

        return in_array($base, self::SUPPORTED, true) ? $base : null;
    }
}
