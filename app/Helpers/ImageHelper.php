<?php

namespace App\Helpers;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use L0n3ly\LaravelDynamicHelpers\Helper;
use Spatie\Image\Image;

class ImageHelper extends Helper
{
    const QUALITY = '85';

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

        Storage::put($tempPath, $contents);

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
        if (Storage::exists($tempPath)) {
            Storage::move($tempPath, $finalPath);
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
        if ($path && Storage::exists($path)) {
            Storage::delete($path);
        }
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
}
