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

    public function moveToFinal(string $tempPath, string $finalPath): string
    {
        if (Storage::exists($tempPath)) {
            Storage::move($tempPath, $finalPath);
        }

        return $finalPath;
    }

    public function deleteImage(?string $path): void
    {
        if ($path && Storage::exists($path)) {
            Storage::delete($path);
        }
    }

    public function generateStoreLogoPath(string $storeId): string
    {
        return "stores/{$storeId}/logo.webp";
    }

    public function generateProductPhotoPath(string $storeId, string $productId): string
    {
        return "stores/{$storeId}/products/{$productId}/photo.webp";
    }

    public function generateProductExtraPhotoPath(string $storeId, string $productId, int $index): string
    {
        return "stores/{$storeId}/products/{$productId}/extra-{$index}.webp";
    }
}
