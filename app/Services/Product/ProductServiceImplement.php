<?php

namespace App\Services\Product;

use App\Enums\ProductStatusEnum;
use App\Enums\ResponseCode;
use App\Enums\StoreStatusEnum;
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

    private function getStoreBySlug(string $storeSlug)
    {
        $store = $this->storeRepository->findBySlug($storeSlug);

        if (! $store || $store->user_id !== Auth::id()) {
            return null;
        }

        return $store;
    }

    private function getPublicStore(string $storeSlug)
    {
        $store = $this->storeRepository->findBySlug($storeSlug);

        if (! $store || $store->status->name !== StoreStatusEnum::ACTIVE->name) {
            return null;
        }

        return $store;
    }

    /** {@inheritDoc} */
    public function getStoreProducts(string $storeSlug): ServiceApi
    {
        try {
            $store = $this->getStoreBySlug($storeSlug);

            if (! $store) {
                return $this->setCode(ResponseCode::FORBIDDEN->value)
                    ->setMessage('Store not found or access denied.');
            }

            $products = $this->productRepository->getStoreProducts($store->id);

            return $this->setCode(ResponseCode::SUCCESS->value)
                ->setMessage('Products retrieved successfully.')
                ->setData([
                    'products' => ProductResource::collection($products),
                    'pagination' => helpers()->queryableHelper()->getPagination($products),
                ]);
        } catch (\Throwable $e) {
            return $this->logAndRespond($e);
        }
    }

    /** {@inheritDoc} */
    public function createProduct(string $storeSlug, array $data): ServiceApi
    {
        try {
            $store = $this->getStoreBySlug($storeSlug);

            if (! $store) {
                return $this->setCode(ResponseCode::FORBIDDEN->value)
                    ->setMessage('Store not found or access denied.');
            }

            $data['store_id'] = $store->id;
            $data['status'] ??= ProductStatusEnum::DRAFT->name;

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
    public function getProduct(string $storeSlug, string $id): ServiceApi
    {
        try {
            $store = $this->getStoreBySlug($storeSlug);

            if (! $store) {
                return $this->setCode(ResponseCode::FORBIDDEN->value)
                    ->setMessage('Store not found or access denied.');
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
    public function updateProduct(string $storeSlug, string $id, array $data): ServiceApi
    {
        try {
            $store = $this->getStoreBySlug($storeSlug);

            if (! $store) {
                return $this->setCode(ResponseCode::FORBIDDEN->value)
                    ->setMessage('Store not found or access denied.');
            }

            $product = $this->productRepository->findOwnedByStore($id, $store->id);

            if (! $product) {
                return $this->setCode(ResponseCode::NOT_FOUND->value)
                    ->setMessage('Product not found.');
            }

            $categoryIds = $data['category_ids'] ?? null;
            unset($data['category_ids']);

            $photoPath = null;
            $extraPhotos = null;

            if (isset($data['photo'])) {
                $photoPath = imageHelper()->storeAndConvert($data['photo'], 'products');
                unset($data['photo']);
            }

            if (isset($data['photos'])) {
                $extraPhotos = [];

                foreach ($data['photos'] as $file) {
                    $extraPhotos[] = imageHelper()->storeAndConvert($file, 'products');
                }
                unset($data['photos']);
            }

            DB::beginTransaction();

            if ($photoPath) {
                $oldPhoto = $product->photo;
                $finalPath = imageHelper()->generateProductPhotoPath($store->id, $product->id);
                $data['photo'] = imageHelper()->moveToFinal($photoPath, $finalPath);

                if ($oldPhoto) {
                    imageHelper()->deleteImage($oldPhoto);
                }
            }

            if ($extraPhotos !== null) {
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

            if (isset($photoPath)) {
                imageHelper()->deleteImage($photoPath);
            }

            if (isset($extraPhotos)) {
                foreach ($extraPhotos as $tempPath) {
                    imageHelper()->deleteImage($tempPath);
                }
            }

            return $this->logAndRespond($e, 'Failed to update product.');
        }
    }

    /** {@inheritDoc} */
    public function deleteProduct(string $storeSlug, string $id): ServiceApi
    {
        try {
            $store = $this->getStoreBySlug($storeSlug);

            if (! $store) {
                return $this->setCode(ResponseCode::FORBIDDEN->value)
                    ->setMessage('Store not found or access denied.');
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
    public function getPublishedProducts(string $storeSlug): ServiceApi
    {
        try {
            $store = $this->getPublicStore($storeSlug);

            if (! $store) {
                return $this->setCode(ResponseCode::NOT_FOUND->value)
                    ->setMessage('Store not found.');
            }

            $products = $this->productRepository->getPublishedProducts($store->id);

            return $this->setCode(ResponseCode::SUCCESS->value)
                ->setMessage('Products retrieved successfully.')
                ->setData([
                    'products' => ProductResource::collection($products),
                    'pagination' => helpers()->queryableHelper()->getPagination($products),
                ]);
        } catch (\Throwable $e) {
            return $this->logAndRespond($e);
        }
    }

    /** {@inheritDoc} */
    public function showPublished(string $id): ServiceApi
    {
        try {
            $product = $this->productRepository->findPublished($id);

            if (! $product) {
                return $this->setCode(ResponseCode::NOT_FOUND->value)
                    ->setMessage('Product not found.');
            }

            return $this->setCode(ResponseCode::SUCCESS->value)
                ->setData(['product' => new ProductResource($product)]);
        } catch (\Throwable $e) {
            return $this->logAndRespond($e);
        }
    }

    /** {@inheritDoc} */
    public function getAdminStoreProducts(string $storeSlug): ServiceApi
    {
        try {
            $store = $this->storeRepository->findBySlug($storeSlug);

            if (! $store) {
                return $this->setCode(ResponseCode::NOT_FOUND->value)
                    ->setMessage('Store not found.');
            }

            $products = $this->productRepository->getStoreProducts($store->id);

            return $this->setCode(ResponseCode::SUCCESS->value)
                ->setMessage('Products retrieved successfully.')
                ->setData([
                    'products' => ProductResource::collection($products),
                    'pagination' => helpers()->queryableHelper()->getPagination($products),
                ]);
        } catch (\Throwable $e) {
            return $this->logAndRespond($e);
        }
    }
}
