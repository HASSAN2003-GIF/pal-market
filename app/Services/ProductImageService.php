<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\SupplierProduct;
use App\Models\User;
use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Stores product photos safely:
 *  - the file type is taken from the file's content, not its name;
 *  - the image is decoded and re-encoded, which drops metadata (including GPS
 *    location from phone cameras) and anything hidden after the image data;
 *  - only resized variants are kept, under random names.
 */
class ProductImageService
{
    private const ALLOWED = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP];

    /**
     * Laravel validation rules for one uploaded photo.
     */
    public static function rules(): array
    {
        $min = config('media.min_dimension');
        $max = config('media.max_dimension');

        return [
            'required',
            'file',
            'max:'.config('media.max_upload_kb'),
            'mimetypes:image/jpeg,image/png,image/webp',
            "dimensions:min_width={$min},min_height={$min},max_width={$max},max_height={$max}",
        ];
    }

    /**
     * @param  list<UploadedFile>  $files
     * @return list<ProductImage>
     */
    public function store(Product|SupplierProduct $owner, array $files, User $uploader, ?string $altText = null): array
    {
        $existing = $owner->images()->count();
        $limit = config('media.max_images_per_item');

        if ($existing + count($files) > $limit) {
            throw ValidationException::withMessages([
                'images' => __('You can add up to :count photos. Remove one to add another.', ['count' => $limit]),
            ]);
        }

        $stored = [];

        try {
            foreach ($files as $i => $file) {
                $stored[] = $this->storeOne($owner, $file, $uploader, $altText, $existing === 0 && $i === 0, $existing + $i);
            }
        } catch (\Throwable $e) {
            // Leave nothing half-saved.
            foreach ($stored as $image) {
                $this->delete($image);
            }
            throw $e;
        }

        return $stored;
    }

    public function makePrimary(ProductImage $image): void
    {
        DB::transaction(function () use ($image) {
            ProductImage::query()
                ->where($image->product_id ? 'product_id' : 'supplier_product_id', $image->product_id ?? $image->supplier_product_id)
                ->update(['is_primary' => false]);
            $image->forceFill(['is_primary' => true])->save();
        });
    }

    public function delete(ProductImage $image): void
    {
        $wasPrimary = $image->is_primary;
        $ownerColumn = $image->product_id ? 'product_id' : 'supplier_product_id';
        $ownerId = $image->product_id ?? $image->supplier_product_id;

        Storage::disk($image->disk)->deleteDirectory($image->directory);
        $image->delete();

        if ($wasPrimary) {
            ProductImage::query()->where($ownerColumn, $ownerId)
                ->orderBy('sort_order')->orderBy('id')
                ->first()?->forceFill(['is_primary' => true])->save();
        }
    }

    private function storeOne(Product|SupplierProduct $owner, UploadedFile $file, User $uploader, ?string $altText, bool $primary, int $order): ProductImage
    {
        $path = $file->getRealPath();
        $info = $path ? @getimagesize($path) : false;

        if (! $info || ! in_array($info[2], self::ALLOWED, true)) {
            throw ValidationException::withMessages(['images' => __('This file is not a JPEG, PNG or WebP photo.')]);
        }

        $max = config('media.max_dimension');
        if ($info[0] > $max || $info[1] > $max) {
            throw ValidationException::withMessages(['images' => __('Photos can be at most :size pixels on the longest side.', ['size' => $max])]);
        }

        $this->ensureMemoryFor($info[0], $info[1]);
        $source = @imagecreatefromstring((string) file_get_contents($path));
        if (! $source instanceof GdImage) {
            throw ValidationException::withMessages(['images' => __('This photo could not be read. Try another file.')]);
        }

        $source = $this->applyOrientation($source, $path, $info[2]);

        $disk = config('media.disk');
        $directory = 'products/'.Str::uuid()->toString();
        $extension = function_exists('imagewebp') ? 'webp' : 'jpg';
        $largeBytes = 0;
        $largeSize = [imagesx($source), imagesy($source)];

        try {
            foreach (config('media.variants') as $variant => $longest) {
                $resized = $this->resize($source, $longest);
                $bytes = $this->encode($resized, $extension);
                Storage::disk($disk)->put("{$directory}/{$variant}.{$extension}", $bytes);

                if ($variant === 'large') {
                    $largeBytes = strlen($bytes);
                    $largeSize = [imagesx($resized), imagesy($resized)];
                }

                unset($resized);
            }
        } catch (\Throwable $e) {
            Storage::disk($disk)->deleteDirectory($directory);
            throw $e;
        } finally {
            unset($source);
        }

        return ProductImage::create([
            'product_id' => $owner instanceof Product ? $owner->id : null,
            'supplier_product_id' => $owner instanceof SupplierProduct ? $owner->id : null,
            'uploaded_by' => $uploader->id,
            'disk' => $disk,
            'directory' => $directory,
            'extension' => $extension,
            'width' => $largeSize[0],
            'height' => $largeSize[1],
            'bytes' => $largeBytes,
            'alt_text' => $altText ? mb_substr(strip_tags($altText), 0, 160) : null,
            'is_primary' => $primary,
            'sort_order' => $order,
        ]);
    }

    /**
     * Decoding needs roughly 5 bytes per pixel plus working copies.
     */
    private function ensureMemoryFor(int $width, int $height): void
    {
        $needed = (int) ($width * $height * 5 * 2.5) + 64 * 1024 * 1024;
        $limit = ini_get('memory_limit');

        if ($limit === '-1' || $limit === false) {
            return;
        }

        $bytes = (int) $limit * match (strtolower(substr($limit, -1))) {
            'g' => 1024 ** 3, 'm' => 1024 ** 2, 'k' => 1024, default => 1,
        };

        if ($bytes < $needed) {
            @ini_set('memory_limit', (string) $needed);
        }
    }

    private function resize(GdImage $source, int $longest): GdImage
    {
        $width = imagesx($source);
        $height = imagesy($source);
        $scale = min(1, $longest / max($width, $height));

        if ($scale >= 1) {
            return $source;
        }

        $newWidth = max(1, (int) round($width * $scale));
        $newHeight = max(1, (int) round($height * $scale));
        $target = imagecreatetruecolor($newWidth, $newHeight);
        imagealphablending($target, false);
        imagesavealpha($target, true);
        imagecopyresampled($target, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        return $target;
    }

    private function encode(GdImage $image, string $extension): string
    {
        ob_start();

        if ($extension === 'webp') {
            imagesavealpha($image, true);
            imagewebp($image, null, 82);
        } else {
            // JPEG has no transparency: flatten onto white.
            $flat = imagecreatetruecolor(imagesx($image), imagesy($image));
            imagefill($flat, 0, 0, imagecolorallocate($flat, 255, 255, 255));
            imagecopy($flat, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));
            imagejpeg($flat, null, 85);
        }

        return (string) ob_get_clean();
    }

    /**
     * Phone cameras store rotation in EXIF instead of rotating pixels.
     */
    private function applyOrientation(GdImage $image, string $path, int $type): GdImage
    {
        if ($type !== IMAGETYPE_JPEG || ! function_exists('exif_read_data')) {
            return $image;
        }

        $orientation = (int) (@exif_read_data($path)['Orientation'] ?? 1);

        $rotated = match ($orientation) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => null,
        };

        return $rotated instanceof GdImage ? $rotated : $image;
    }
}
