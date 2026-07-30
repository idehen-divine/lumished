<?php

namespace App\Repositories\StoreSettings;

use App\Models\StoreSettings;
use L0n3ly\LaravelRepositoryWithService\Implementations\Eloquent;

class StoreSettingsRepositoryImplement extends Eloquent implements StoreSettingsRepository
{
    protected StoreSettings $model;

    public function __construct(StoreSettings $model)
    {
        $this->model = $model;
    }

    /** {@inheritDoc} */
    public function getByStoreId(string $storeId): ?StoreSettings
    {
        return $this->model->where('store_id', $storeId)->first();
    }
}
