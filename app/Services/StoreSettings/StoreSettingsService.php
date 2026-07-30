<?php

namespace App\Services\StoreSettings;

use L0n3ly\LaravelRepositoryWithService\Contracts\BaseService;
use L0n3ly\LaravelRepositoryWithService\Services\ServiceApi;

interface StoreSettingsService extends BaseService
{
    /**
     * Get the authenticated user store settings.
     *
     * @return ServiceApi Response with settings data or error
     */
    public function getSettings(): ServiceApi;

    /**
     * Update the authenticated user store settings.
     *
     * @param  array  $data  The settings data to update
     * @return ServiceApi Response with updated settings or error
     */
    public function updateSettings(array $data): ServiceApi;
}
