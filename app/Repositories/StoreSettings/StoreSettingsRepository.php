<?php

namespace App\Repositories\StoreSettings;

use App\Models\StoreSettings;
use L0n3ly\LaravelRepositoryWithService\Contracts\Repository;

interface StoreSettingsRepository extends Repository
{
    /**
     * Get settings by store ID.
     *
     * @param  string  $storeId  The store UUID
     * @return StoreSettings|null The settings or null if not found
     */
    public function getByStoreId(string $storeId): ?StoreSettings;
}
