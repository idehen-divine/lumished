<?php

namespace App\Services\StoreSettings;

use App\Enums\ResponseCode;
use App\Http\Resources\StoreSettingsResource;
use App\Repositories\Store\StoreRepository;
use App\Repositories\StoreSettings\StoreSettingsRepository;
use App\Traits\LogAndRespond;
use Illuminate\Support\Facades\Auth;
use L0n3ly\LaravelRepositoryWithService\Services\ServiceApi;

class StoreSettingsServiceImplement extends ServiceApi implements StoreSettingsService
{
    use LogAndRespond;

    protected StoreSettingsRepository $mainRepository;

    public function __construct(
        StoreSettingsRepository $mainRepository,
        protected StoreRepository $storeRepository,
    ) {
        $this->mainRepository = $mainRepository;
    }

    /** {@inheritDoc} */
    public function getSettings(): ServiceApi
    {
        try {
            $store = $this->storeRepository->getStoreForUser(Auth::id());

            if (! $store) {
                return $this->setCode(ResponseCode::NOT_FOUND->value)
                    ->setMessage('You do not have a store yet.');
            }

            $settings = $this->mainRepository->getByStoreId($store->id);

            if (! $settings) {
                return $this->setCode(ResponseCode::NOT_FOUND->value)
                    ->setMessage('Settings not found.');
            }

            return $this->setCode(ResponseCode::SUCCESS->value)
                ->setData(['settings' => new StoreSettingsResource($settings)]);
        } catch (\Throwable $e) {
            return $this->logAndRespond($e);
        }
    }

    /** {@inheritDoc} */
    public function updateSettings(array $data): ServiceApi
    {
        try {
            $store = $this->storeRepository->getStoreForUser(Auth::id());

            if (! $store) {
                return $this->setCode(ResponseCode::NOT_FOUND->value)
                    ->setMessage('You do not have a store yet.');
            }

            $settings = $this->mainRepository->getByStoreId($store->id);

            if (! $settings) {
                return $this->setCode(ResponseCode::NOT_FOUND->value)
                    ->setMessage('Settings not found.');
            }

            $this->mainRepository->update($settings->id, $data);

            $settings = $this->mainRepository->find($settings->id);

            return $this->setCode(ResponseCode::SUCCESS->value)
                ->setMessage('Settings updated successfully.')
                ->setData(['settings' => new StoreSettingsResource($settings)]);
        } catch (\Throwable $e) {
            return $this->logAndRespond($e, 'Failed to update settings.');
        }
    }
}
