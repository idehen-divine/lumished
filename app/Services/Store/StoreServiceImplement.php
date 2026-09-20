<?php

namespace App\Services\Store;

use App\Enums\ResponseCode;
use App\Enums\StoreStatusEnum;
use App\Http\Resources\StoreResource;
use App\Repositories\Store\StoreRepository;
use App\Traits\LogAndRespond;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use L0n3ly\LaravelRepositoryWithService\Services\ServiceApi;

class StoreServiceImplement extends ServiceApi implements StoreService
{
    use LogAndRespond;

    public function __construct(
        protected StoreRepository $storeRepository,
    ) {}

    /** {@inheritDoc} */
    public function createStore(array $data): ServiceApi
    {
        try {
            if ($this->storeRepository->getStoreForUser(Auth::id())) {
                return $this->setCode(ResponseCode::VALIDATION_ERROR->value)
                    ->setMessage('You already have a store. Only one store per account is allowed.');
            }

            $data['user_id'] = Auth::id();
            $data['status'] = StoreStatusEnum::ACTIVE->name;

            if (isset($data['logo'])) {
                $logoPath = imageHelper()->storeAndConvert($data['logo'], 'stores');
                unset($data['logo']);
            }

            DB::beginTransaction();

            $store = $this->storeRepository->create($data);

            if (isset($logoPath)) {
                $finalPath = imageHelper()->generateStoreLogoPath($store->id);
                $storePath = imageHelper()->moveToFinal($logoPath, $finalPath);
                $this->storeRepository->update($store->id, ['logo_url' => $storePath]);
                $store->logo_url = $storePath;
            }

            DB::commit();

            return $this->setCode(ResponseCode::CREATED->value)
                ->setMessage('Store created successfully.')
                ->setData(['store' => new StoreResource($store)]);
        } catch (\Throwable $e) {
            DB::rollBack();

            if (isset($logoPath)) {
                imageHelper()->deleteImage($logoPath);
            }

            return $this->logAndRespond($e, 'Failed to create store.');
        }
    }

    /** {@inheritDoc} */
    public function getUserStore(): ServiceApi
    {
        try {
            $store = $this->storeRepository->getStoreForUser(Auth::id());

            if (! $store) {
                return $this->setCode(ResponseCode::NOT_FOUND->value)
                    ->setMessage('You do not have a store yet.');
            }

            return $this->setCode(ResponseCode::SUCCESS->value)
                ->setData(['store' => new StoreResource($store)]);
        } catch (\Throwable $e) {
            return $this->logAndRespond($e);
        }
    }

    /** {@inheritDoc} */
    public function updateStore(array $data): ServiceApi
    {
        try {
            $store = $this->storeRepository->getStoreForUser(Auth::id());

            if (! $store) {
                return $this->setCode(ResponseCode::NOT_FOUND->value)
                    ->setMessage('Store not found.');
            }

            if (isset($data['logo'])) {
                $logoPath = imageHelper()->storeAndConvert($data['logo'], 'stores');
                unset($data['logo']);

                DB::beginTransaction();

                $oldLogo = $store->logo_url;
                $finalPath = imageHelper()->generateStoreLogoPath($store->id);
                $storePath = imageHelper()->moveToFinal($logoPath, $finalPath);
                $data['logo_url'] = $storePath;

                $this->storeRepository->update($store->id, $data);

                DB::commit();

                if ($oldLogo) {
                    imageHelper()->deleteImage($oldLogo);
                }
            } else {
                $this->storeRepository->update($store->id, $data);
            }

            $store = $this->storeRepository->find($store->id);

            return $this->setCode(ResponseCode::SUCCESS->value)
                ->setMessage('Store updated successfully.')
                ->setData(['store' => new StoreResource($store)]);
        } catch (\Throwable $e) {
            DB::rollBack();

            return $this->logAndRespond($e, 'Failed to update store.');
        }
    }

    /** {@inheritDoc} */
    public function deleteStore(): ServiceApi
    {
        try {
            $store = $this->storeRepository->getStoreForUser(Auth::id());

            if (! $store) {
                return $this->setCode(ResponseCode::NOT_FOUND->value)
                    ->setMessage('Store not found.');
            }

            // Photos grouped under stores/{storeId}/ for easy folder cleanup
            imageHelper()->deleteDirectory(imageHelper()->generateStoreDirectory($store->id));

            // Fallback: delete logo individually if directory delete misses
            if ($store->logo_url) {
                imageHelper()->deleteImage($store->logo_url);
            }

            $this->storeRepository->delete($store->id);

            return $this->setCode(ResponseCode::SUCCESS->value)
                ->setMessage('Store deleted successfully.');
        } catch (\Throwable $e) {
            return $this->logAndRespond($e, 'Failed to delete store.');
        }
    }

    /** {@inheritDoc} */
    public function showForPublicBySlugOrDomain(?string $slug, ?string $domain): ServiceApi
    {
        try {
            $store = $this->storeRepository->findActiveBySlugOrDomain($slug, $domain);

            if (! $store) {
                return $this->setCode(ResponseCode::NOT_FOUND->value)
                    ->setMessage('Store not found.');
            }

            return $this->setCode(ResponseCode::SUCCESS->value)
                ->setData(['store' => new StoreResource($store)]);
        } catch (\Throwable $e) {
            return $this->logAndRespond($e);
        }
    }

    /** {@inheritDoc} */
    public function getAllForAdmin(): ServiceApi
    {
        try {
            $stores = $this->storeRepository->getAllForAdmin();

            return $this->setCode(ResponseCode::SUCCESS->value)
                ->setMessage('Stores retrieved successfully.')
                ->setData([
                    'stores' => StoreResource::collection($stores),
                    'pagination' => queryableHelper()->getPagination($stores),
                ]);
        } catch (\Throwable $e) {
            return $this->logAndRespond($e);
        }
    }

    /** {@inheritDoc} */
    public function showForAdmin(string $id): ServiceApi
    {
        try {
            $store = $this->storeRepository->find($id);

            if (! $store) {
                return $this->setCode(ResponseCode::NOT_FOUND->value)
                    ->setMessage('Store not found.');
            }

            return $this->setCode(ResponseCode::SUCCESS->value)
                ->setData(['store' => new StoreResource($store)]);
        } catch (\Throwable $e) {
            return $this->logAndRespond($e);
        }
    }

    /** {@inheritDoc} */
    public function updateStatus(string $id, string $status): ServiceApi
    {
        try {
            $store = $this->storeRepository->find($id);

            if (! $store) {
                return $this->setCode(ResponseCode::NOT_FOUND->value)
                    ->setMessage('Store not found.');
            }

            $this->storeRepository->update($id, ['status' => $status]);

            $store = $this->storeRepository->find($id);

            return $this->setCode(ResponseCode::SUCCESS->value)
                ->setMessage('Store status updated successfully.')
                ->setData(['store' => new StoreResource($store)]);
        } catch (\Throwable $e) {
            return $this->logAndRespond($e, 'Failed to update store status.');
        }
    }
}
