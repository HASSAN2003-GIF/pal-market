<?php

namespace App\Http\Controllers;

use App\Models\ProductImage;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Streams a stored product photo. Only paths recorded in product_images are
 * served, so nothing else on the media disk can be reached through here.
 */
class MediaController extends Controller
{
    public function __invoke(string $directory, string $variant, string $extension): Response
    {
        $image = ProductImage::query()
            ->where('directory', "products/{$directory}")
            ->where('extension', $extension)
            ->firstOrFail();

        $path = $image->path($variant);
        $disk = Storage::disk($image->disk);

        abort_unless($disk->exists($path), 404);

        return $disk->response($path, null, [
            'Content-Type' => $extension === 'webp' ? 'image/webp' : 'image/jpeg',
            // Names are random and never reused, so the file never changes.
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
