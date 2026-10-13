<?php

/*
 * The SPA is served from the same origin as the API, so cross-origin access is
 * closed by default. List additional trusted origins (comma separated) in
 * CORS_ALLOWED_ORIGINS only if a separately hosted client needs the API.
 * Authentication uses bearer tokens, so credentials are never shared.
 */
return [
    'paths' => ['api/*'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('CORS_ALLOWED_ORIGINS', env('APP_URL', ''))),
    ))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Accept', 'Authorization', 'Content-Type', 'X-Requested-With'],

    'exposed_headers' => [],

    'max_age' => 3600,

    'supports_credentials' => false,
];
