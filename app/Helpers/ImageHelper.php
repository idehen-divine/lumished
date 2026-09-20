<?php

namespace App\Helpers;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use L0n3ly\LaravelDynamicHelpers\Helper;
use Spatie\Image\Image;

class ImageHelper extends Helper
{
    const QUALITY = '85';

    /**
     * Get the filesystem disk used for images (S3).
     *
     * @return Filesystem|FilesystemAdapter
     */
    private function disk()
    {
        $diskName = config('filesystems.default', 's3');

        return Storage::disk($diskName);
    }

    /**
     * Store an uploaded file as a WebP image in the temp directory.
     *
     * @param  UploadedFile  $file  The uploaded image file
     * @param  string  $directory  The temp directory path
     * @return string The temp storage path of the converted image
     */
    public function storeAndConvert(UploadedFile $file, string $directory): string
    {
        $tempPath = 'temp/'.Str::uuid().'.webp';

        $tmpFile = tempnam(sys_get_temp_dir(), 'img').'.webp';

        Image::load($file->getPathname())
            ->format('webp')
            ->quality((int) self::QUALITY)
            ->save($tmpFile);

        $contents = file_get_contents($tmpFile);
        @unlink($tmpFile);

        $this->disk()->put($tempPath, $contents);

        return $tempPath;
    }

    /**
     * Move a file from a temp path to its final storage location.
     *
     * @param  string  $tempPath  The current temp storage path
     * @param  string  $finalPath  The destination storage path
     * @return string The final storage path
     */
    public function moveToFinal(string $tempPath, string $finalPath): string
    {
        if ($this->disk()->exists($tempPath)) {
            $this->disk()->move($tempPath, $finalPath);
        }

        return $finalPath;
    }

    /**
     * Delete an image file from storage.
     *
     * @param  string|null  $path  The storage path to delete
     */
    public function deleteImage(?string $path): void
    {
        if ($path && $this->disk()->exists($path)) {
            $this->disk()->delete($path);
        }
    }

    /**
     * Delete a directory and all its contents from storage.
     *
     * Useful because photos are grouped under stores/{storeId}/products/{productId}/ for easy cleanup.
     *
     * @param  string  $directory  The storage directory prefix to delete
     */
    public function deleteDirectory(string $directory): void
    {
        $directory = trim($directory, '/');

        if ($directory === '') {
            return;
        }

        $this->disk()->deleteDirectory($directory);
    }

    /**
     * Generate the storage directory for a product's photos.
     *
     * @param  string  $storeId  The store UUID
     * @param  string  $productId  The product UUID
     * @return string The product photos directory
     */
    public function generateProductPhotosDirectory(string $storeId, string $productId): string
    {
        return "stores/{$storeId}/products/{$productId}";
    }

    /**
     * Generate the storage directory for a store.
     *
     * @param  string  $storeId  The store UUID
     * @return string The store directory
     */
    public function generateStoreDirectory(string $storeId): string
    {
        return "stores/{$storeId}";
    }

    /**
     * Get the full URL for an image path.
     *
     * Converts a relative storage path (e.g. stores/.../photo.webp) to a full absolute URL
     * via the S3 filesystem disk. If the path is already a URL, it is returned as-is.
     * Returns null when the path is empty so callers can preserve nullable semantics.
     *
     * @param  string|null  $path  The storage path or existing URL
     * @return string|null The full URL or null if no path
     */
    public function getUrl(?string $path): ?string
    {
        if (empty($path)) {
            return null;
        }

        if (filter_var($path, FILTER_VALIDATE_URL)) {
            return $path;
        }

        $url = $this->disk()->url($path);

        // Handle S3 path-style endpoint where bucket may be missing from URL when AWS_URL is set without bucket.
        $diskName = config('filesystems.default', 's3');
        if ($diskName === 's3' && filter_var($url, FILTER_VALIDATE_URL)) {
            $bucket = config('filesystems.disks.s3.bucket');
            $usePathStyle = config('filesystems.disks.s3.use_path_style_endpoint');
            $urlPath = parse_url($url, PHP_URL_PATH) ?? '';
            $host = parse_url($url, PHP_URL_HOST) ?? '';
            $hasBucketInPath = $bucket && str_contains($urlPath, "/{$bucket}/") || $bucket && $urlPath === "/{$bucket}";
            $hasBucketInHost = $bucket && str_contains($host, $bucket);

            if ($bucket && $usePathStyle && ! $hasBucketInPath && ! $hasBucketInHost) {
                $parsed = parse_url($url);
                $scheme = $parsed['scheme'] ?? 'https';
                $port = isset($parsed['port']) ? ':'.$parsed['port'] : '';
                $pathPart = $parsed['path'] ?? '';
                $newPath = '/'.trim($bucket, '/').'/'.ltrim($pathPart, '/');
                $url = $scheme.'://'.$host.$port.$newPath;
                if (isset($parsed['query'])) {
                    $url .= '?'.$parsed['query'];
                }
                if (isset($parsed['fragment'])) {
                    $url .= '#'.$parsed['fragment'];
                }
            }
        }

        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            $url = rtrim(config('app.url'), '/').'/'.ltrim($url, '/');
        }

        return $url;
    }

    /**
     * Get the full URL for an image path with fallback placeholder.
     *
     * @param  string|null  $path  The image path
     * @param  string  $title  Title for placeholder if image not found
     * @return string The image URL or placeholder URL
     */
    public function getImageUrl(?string $path, string $title = 'image'): string
    {
        $url = $this->getUrl($path);

        if ($url) {
            return $url;
        }

        $encodedTitle = urlencode($title);

        return "https://placehold.co/800x800/d5d5d5/000000?text={$encodedTitle}";
    }

    /**
     * Get full URLs for a collection of image paths.
     *
     * @param  array|null  $paths  Array of image paths
     * @return array Array of valid image URLs
     */
    public function getImageCollectionUrls(?array $paths): array
    {
        if (empty($paths) || ! is_array($paths)) {
            return [];
        }

        return array_values(array_filter(array_map(fn (?string $path) => $this->getUrl($path), $paths)));
    }

    /**
     * Generate the storage path for a store logo.
     *
     * @param  string  $storeId  The store UUID
     * @return string The generated storage path
     */
    public function generateStoreLogoPath(string $storeId): string
    {
        return "stores/{$storeId}/logo.webp";
    }

    /**
     * Generate the storage path for a product photo.
     *
     * @param  string  $storeId  The store UUID
     * @param  string  $productId  The product UUID
     * @return string The generated storage path
     */
    public function generateProductPhotoPath(string $storeId, string $productId): string
    {
        return "stores/{$storeId}/products/{$productId}/photo.webp";
    }

    /**
     * Generate the storage path for an extra product photo.
     *
     * @param  string  $storeId  The store UUID
     * @param  string  $productId  The product UUID
     * @param  int  $index  The photo index (0-based)
     * @return string The generated storage path
     */
    public function generateProductExtraPhotoPath(string $storeId, string $productId, int $index): string
    {
        return "stores/{$storeId}/products/{$productId}/extra-{$index}.webp";
    }
}
