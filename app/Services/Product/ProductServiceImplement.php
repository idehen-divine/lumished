<?php

namespace App\Services\Product;

use App\Enums\ProductStatusEnum;
use App\Enums\ResponseCode;
use App\Http\Resources\ProductResource;
use App\Repositories\Product\ProductRepository;
use App\Repositories\Store\StoreRepository;
use App\Traits\LogAndRespond;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use L0n3ly\LaravelRepositoryWithService\Services\ServiceApi;

class ProductServiceImplement extends ServiceApi implements ProductService
{
    use LogAndRespond;

    public function __construct(
        protected StoreRepository $storeRepository,
        protected ProductRepository $productRepository,
    ) {}

    private function getUserStore()
    {
        return $this->storeRepository->getStoreForUser(Auth::id());
    }

    /** {@inheritDoc} */
    public function getStoreProducts(): ServiceApi
    {
        try {
            $store = $this->getUserStore();

            if (! $store) {
                return $this->setCode(ResponseCode::NOT_FOUND->value)
                    ->setMessage('You do not have a store yet.');
            }

            $products = $this->productRepository->getStoreProducts($store->id);

            return $this->setCode(ResponseCode::SUCCESS->value)
                ->setMessage('Products retrieved successfully.')
                ->setData([
                    'products' => ProductResource::collection($products),
                    'pagination' => queryableHelper()->getPagination($products),
                ]);
        } catch (\Throwable $e) {
            return $this->logAndRespond($e);
        }
    }

    /** {@inheritDoc} */
    public function createProduct(array $data): ServiceApi
    {
        try {
            $store = $this->getUserStore();

            if (! $store) {
                return $this->setCode(ResponseCode::NOT_FOUND->value)
                    ->setMessage('You do not have a store yet.');
            }

            $data['store_id'] = $store->id;
            $data['status'] ??= ProductStatusEnum::DRAFT->name;
            $data['price'] = moneyHelper()->toMinor($data['price']);

            if (array_key_exists('compare_at_price', $data)) {
                $data['compare_at_price'] = moneyHelper()->toMinor($data['compare_at_price']);
            }

            $categoryIds = $data['category_ids'] ?? [];
            unset($data['category_ids']);

            $photoPath = null;
            $extraPhotos = [];

            if (isset($data['photo'])) {
                $photoPath = imageHelper()->storeAndConvert($data['photo'], 'products');
                unset($data['photo']);
            }

            if (isset($data['photos'])) {
                foreach ($data['photos'] as $file) {
                    $extraPhotos[] = imageHelper()->storeAndConvert($file, 'products');
                }
                unset($data['photos']);
            }

            DB::beginTransaction();

            $product = $this->productRepository->create($data);

            if ($photoPath) {
                $finalPath = imageHelper()->generateProductPhotoPath($store->id, $product->id);
                $finalPhoto = imageHelper()->moveToFinal($photoPath, $finalPath);
                $this->productRepository->update($product->id, ['photo' => $finalPhoto]);
                $product->photo = $finalPhoto;
            }

            if (! empty($extraPhotos)) {
                $finalExtraPhotos = [];

                foreach ($extraPhotos as $index => $tempPath) {
                    $finalPath = imageHelper()->generateProductExtraPhotoPath($store->id, $product->id, $index);
                    $finalExtraPhotos[] = imageHelper()->moveToFinal($tempPath, $finalPath);
                }

                $this->productRepository->update($product->id, ['photos' => $finalExtraPhotos]);
                $product->photos = $finalExtraPhotos;
            }

            if (! empty($categoryIds)) {
                $product->categories()->sync($categoryIds);
            }

            DB::commit();

            $product->load('categories');

            return $this->setCode(ResponseCode::CREATED->value)
                ->setMessage('Product created successfully.')
                ->setData(['product' => new ProductResource($product)]);
        } catch (\Throwable $e) {
            DB::rollBack();

            if (isset($photoPath)) {
                imageHelper()->deleteImage($photoPath);
            }

            foreach ($extraPhotos ?? [] as $tempPath) {
                imageHelper()->deleteImage($tempPath);
            }

            return $this->logAndRespond($e, 'Failed to create product.');
        }
    }

    /** {@inheritDoc} */
    public function getProduct(string $id): ServiceApi
    {
        try {
            $store = $this->getUserStore();

            if (! $store) {
                return $this->setCode(ResponseCode::NOT_FOUND->value)
                    ->setMessage('You do not have a store yet.');
            }

            $product = $this->productRepository->findOwnedByStore($id, $store->id);

            if (! $product) {
                return $this->setCode(ResponseCode::NOT_FOUND->value)
                    ->setMessage('Product not found.');
            }

            $product->load('categories');

            return $this->setCode(ResponseCode::SUCCESS->value)
                ->setData(['product' => new ProductResource($product)]);
        } catch (\Throwable $e) {
            return $this->logAndRespond($e);
        }
    }

    /** {@inheritDoc} */
    public function updateProduct(string $id, array $data): ServiceApi
    {
        try {
            $store = $this->getUserStore();

            if (! $store) {
                return $this->setCode(ResponseCode::NOT_FOUND->value)
                    ->setMessage('You do not have a store yet.');
            }

            $product = $this->productRepository->findOwnedByStore($id, $store->id);

            if (! $product) {
                return $this->setCode(ResponseCode::NOT_FOUND->value)
                    ->setMessage('Product not found.');
            }

            $categoryIds = $data['category_ids'] ?? null;
            unset($data['category_ids']);

            if (array_key_exists('price', $data)) {
                $data['price'] = moneyHelper()->toMinor($data['price']);
            }

            if (array_key_exists('compare_at_price', $data)) {
                $data['compare_at_price'] = moneyHelper()->toMinor($data['compare_at_price']);
            }

            $photoPath = null;
            $removePhoto = false;
            $clearPhotos = false;
            $extraPhotos = null;
            $patchOperations = null;
            $patchTempPaths = [];

            if (array_key_exists('photo', $data)) {
                if ($data['photo'] === null) {
                    $removePhoto = true;
                } elseif (isset($data['photo'])) {
                    $photoPath = imageHelper()->storeAndConvert($data['photo'], 'products');
                }
                unset($data['photo']);
            }

            if (array_key_exists('photos', $data)) {
                $photosValue = $data['photos'];

                if ($photosValue === null || (is_array($photosValue) && empty($photosValue))) {
                    $clearPhotos = true;
                    $extraPhotos = [];
                } elseif (is_array($photosValue)) {
                    $hasNull = in_array(null, $photosValue, true);
                    $keys = array_keys($photosValue);
                    $isSequential = $keys === range(0, count($photosValue) - 1) && ! $hasNull;

                    // Support 1-based indices like [1=>null,2=>file] -> normalize to 0-based if no 0 key
                    $normalizeShift = false;
                    if (! $isSequential && ! empty($keys)) {
                        $minKey = min(array_map('intval', $keys));
                        $maxKey = max(array_map('intval', $keys));
                        if ($minKey === 1 && ! in_array(0, $keys, true) && $maxKey <= 3) {
                            $normalizeShift = true;
                        }
                    }

                    if ($isSequential) {
                        // Full replace: photos[] => replaces all
                        $extraPhotos = [];

                        foreach ($photosValue as $file) {
                            $extraPhotos[] = imageHelper()->storeAndConvert($file, 'products');
                        }
                    } else {
                        // Partial indexed patch: photos[1=>null] or photos[2=>file]
                        $patchOperations = [];
                        $patchTempPaths = [];

                        foreach ($photosValue as $k => $v) {
                            $idx = (int) $k;
                            if ($normalizeShift) {
                                $idx -= 1;
                            }

                            if ($v === null) {
                                $patchOperations[$idx] = null;
                            } else {
                                $tempPath = imageHelper()->storeAndConvert($v, 'products');
                                $patchOperations[$idx] = $tempPath;
                                $patchTempPaths[] = $tempPath;
                            }
                        }
                    }
                }
                unset($data['photos']);
            }

            DB::beginTransaction();

            if ($removePhoto) {
                if ($product->photo) {
                    imageHelper()->deleteImage($product->photo);
                }
                $data['photo'] = null;
            } elseif ($photoPath) {
                $oldPhoto = $product->photo;
                $finalPath = imageHelper()->generateProductPhotoPath($store->id, $product->id);
                $data['photo'] = imageHelper()->moveToFinal($photoPath, $finalPath);

                if ($oldPhoto) {
                    imageHelper()->deleteImage($oldPhoto);
                }
            }

            if ($clearPhotos) {
                $oldPhotos = $product->photos ?? [];
                $data['photos'] = null;

                foreach ($oldPhotos as $oldPath) {
                    imageHelper()->deleteImage($oldPath);
                }
            } elseif ($patchOperations !== null) {
                $oldPhotos = $product->photos ?? [];
                // Ensure oldPhotos is indexed 0..n-1
                $oldPhotos = array_values($oldPhotos);

                foreach ($patchOperations as $idx => $tempOrNull) {
                    if ($tempOrNull === null) {
                        if (isset($oldPhotos[$idx])) {
                            imageHelper()->deleteImage($oldPhotos[$idx]);
                            unset($oldPhotos[$idx]);
                        }
                    } else {
                        $finalPath = imageHelper()->generateProductExtraPhotoPath($store->id, $product->id, $idx);
                        $finalPhoto = imageHelper()->moveToFinal($tempOrNull, $finalPath);

                        if (isset($oldPhotos[$idx]) && $oldPhotos[$idx]) {
                            imageHelper()->deleteImage($oldPhotos[$idx]);
                        }

                        $oldPhotos[$idx] = $finalPhoto;
                    }
                }

                // Reindex to sequential and filter gaps, ensure max 3
                ksort($oldPhotos);
                $newPhotos = array_values(array_filter($oldPhotos, fn ($v) => $v !== null && $v !== ''));

                if (count($newPhotos) > 3) {
                    // Clean up newly moved files that would exceed limit
                    foreach (array_slice($newPhotos, 3) as $excess) {
                        imageHelper()->deleteImage($excess);
                    }
                    $newPhotos = array_slice($newPhotos, 0, 3);
                }

                $data['photos'] = empty($newPhotos) ? null : $newPhotos;

                // Remove used temp paths from tracking to avoid double delete in catch
                $patchTempPaths = [];
            } elseif ($extraPhotos !== null) {
                $oldPhotos = $product->photos ?? [];
                $finalExtraPhotos = [];

                foreach ($extraPhotos as $index => $tempPath) {
                    $finalPath = imageHelper()->generateProductExtraPhotoPath($store->id, $product->id, $index);
                    $finalExtraPhotos[] = imageHelper()->moveToFinal($tempPath, $finalPath);
                }

                $data['photos'] = $finalExtraPhotos;

                foreach ($oldPhotos as $oldPath) {
                    imageHelper()->deleteImage($oldPath);
                }
            }

            $this->productRepository->update($id, $data);

            if ($categoryIds !== null) {
                $product->categories()->sync($categoryIds);
            }

            DB::commit();

            $product = $this->productRepository->find($id);
            $product->load('categories');

            return $this->setCode(ResponseCode::SUCCESS->value)
                ->setMessage('Product updated successfully.')
                ->setData(['product' => new ProductResource($product)]);
        } catch (\Throwable $e) {
            DB::rollBack();

            if (isset($photoPath) && $photoPath) {
                imageHelper()->deleteImage($photoPath);
            }

            if (isset($extraPhotos) && is_array($extraPhotos)) {
                foreach ($extraPhotos as $tempPath) {
                    imageHelper()->deleteImage($tempPath);
                }
            }

            if (isset($patchTempPaths) && is_array($patchTempPaths)) {
                foreach ($patchTempPaths as $tempPath) {
                    imageHelper()->deleteImage($tempPath);
                }
            }

            if (isset($patchOperations) && is_array($patchOperations)) {
                foreach ($patchOperations as $tempPath) {
                    if (is_string($tempPath)) {
                        imageHelper()->deleteImage($tempPath);
                    }
                }
            }

            return $this->logAndRespond($e, 'Failed to update product.');
        }
    }

    /** {@inheritDoc} */
    public function deleteProduct(string $id): ServiceApi
    {
        try {
            $store = $this->getUserStore();

            if (! $store) {
                return $this->setCode(ResponseCode::NOT_FOUND->value)
                    ->setMessage('You do not have a store yet.');
            }

            $product = $this->productRepository->findOwnedByStore($id, $store->id);

            if (! $product) {
                return $this->setCode(ResponseCode::NOT_FOUND->value)
                    ->setMessage('Product not found.');
            }

            if ($product->photo) {
                imageHelper()->deleteImage($product->photo);
            }

            foreach ($product->photos ?? [] as $photo) {
                imageHelper()->deleteImage($photo);
            }

            $this->productRepository->delete($id);

            return $this->setCode(ResponseCode::SUCCESS->value)
                ->setMessage('Product deleted successfully.');
        } catch (\Throwable $e) {
            return $this->logAndRespond($e, 'Failed to delete product.');
        }
    }

    /** {@inheritDoc} */
    public function getPublishedProducts(string $storeId): ServiceApi
    {
        try {
            $products = $this->productRepository->getPublishedProducts($storeId);

            return $this->setCode(ResponseCode::SUCCESS->value)
                ->setMessage('Products retrieved successfully.')
                ->setData([
                    'products' => ProductResource::collection($products),
                    'pagination' => queryableHelper()->getPagination($products),
                ]);
        } catch (\Throwable $e) {
            return $this->logAndRespond($e);
        }
    }

    /** {@inheritDoc} */
    public function getPublishedProductsBySlugOrDomain(?string $slug, ?string $domain): ServiceApi
    {
        try {
            $store = $this->storeRepository->findActiveBySlugOrDomain($slug, $domain);

            if (! $store) {
                return $this->setCode(ResponseCode::NOT_FOUND->value)
                    ->setMessage('Store not found.');
            }

            return $this->getPublishedProducts($store->id);
        } catch (\Throwable $e) {
            return $this->logAndRespond($e);
        }
    }

    /** {@inheritDoc} */
    public function getAdminStoreProducts(string $storeId): ServiceApi
    {
        try {
            $products = $this->productRepository->getStoreProducts($storeId);

            return $this->setCode(ResponseCode::SUCCESS->value)
                ->setMessage('Products retrieved successfully.')
                ->setData([
                    'products' => ProductResource::collection($products),
                    'pagination' => queryableHelper()->getPagination($products),
                ]);
        } catch (\Throwable $e) {
            return $this->logAndRespond($e);
        }
    }

    /** {@inheritDoc} */
    public function deleteStoreProducts(string $storeId): ServiceApi
    {
        try {
            $products = $this->productRepository->query()->where('store_id', $storeId)->get(['id', 'photo', 'photos']);

            foreach ($products as $product) {
                if ($product->photo) {
                    imageHelper()->deleteImage($product->photo);
                }

                foreach ($product->photos ?? [] as $photo) {
                    imageHelper()->deleteImage($photo);
                }
            }

            $this->productRepository->deleteByStore($storeId);

            return $this->setCode(ResponseCode::SUCCESS->value)
                ->setMessage('Store products deleted successfully.');
        } catch (\Throwable $e) {
            return $this->logAndRespond($e, 'Failed to delete store products.');
        }
    }

    /** {@inheritDoc} */
    public function deleteUserStoreProducts(): ServiceApi
    {
        $store = $this->getUserStore();

        if (! $store) {
            return $this->setCode(ResponseCode::NOT_FOUND->value)
                ->setMessage('You do not have a store yet.');
        }

        return $this->deleteStoreProducts($store->id);
    }
}
