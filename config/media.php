<?php

return [
    /*
    | Filesystem disk for product photos. "public" (storage/app/public) works
    | on a single server; use "s3" (or another shared disk) when running more
    | than one web server. Photos are always served through /media/... on the
    | app's own origin, so no storage:link or extra CSP host is needed.
    */
    'disk' => env('MEDIA_DISK', 'public'),

    // Upload limits, enforced on the server.
    'max_upload_kb' => (int) env('MEDIA_MAX_UPLOAD_KB', 5120),
    'max_images_per_item' => 8,
    'min_dimension' => 200,
    'max_dimension' => 4096,

    // Stored variants (longest side, pixels).
    'variants' => [
        'thumb' => 480,
        'large' => 1600,
    ],
];
