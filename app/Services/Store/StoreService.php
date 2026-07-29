<?php

namespace App\Services\Store;

use L0n3ly\LaravelRepositoryWithService\Contracts\BaseService;
use L0n3ly\LaravelRepositoryWithService\Services\ServiceApi;

interface StoreService extends BaseService
{
    /**
     * Create a new store for the authenticated customer.
     *
     * @param  array  $data  The store creation data (name, description, address, etc.)
     * @return ServiceApi The service API response with the created store resource
     */
    public function createStore(array $data): ServiceApi;

    /**
     * Retrieve all stores owned by the authenticated customer.
     *
     * @return ServiceApi The service API response with a paginated list of stores
     */
    public function getUserStores(): ServiceApi;

    /**
     * Retrieve a single store by its UUID.
     *
     * @param  string  $id  The store UUID
     * @return ServiceApi The service API response with the store resource
     */
    public function getStore(string $id): ServiceApi;

    /**
     * Update an existing store.
     *
     * @param  string  $id  The store UUID
     * @param  array  $data  The store update data
     * @return ServiceApi The service API response with the updated store resource
     */
    public function updateStore(string $id, array $data): ServiceApi;

    /**
     * Delete a store and its associated resources.
     *
     * @param  string  $id  The store UUID
     * @return ServiceApi The service API response indicating deletion success
     */
    public function deleteStore(string $id): ServiceApi;

    /**
     * Retrieve all active stores for public browsing.
     *
     * @return ServiceApi The service API response with a paginated list of active stores
     */
    public function getAllActive(): ServiceApi;

    /**
     * Retrieve a single active store by its slug.
     *
     * @param  string  $slug  The store slug
     * @return ServiceApi The service API response with the public store resource
     */
    public function showBySlug(string $slug): ServiceApi;

    /**
     * Retrieve all stores for admin oversight.
     *
     * Includes all stores regardless of status.
     *
     * @return ServiceApi The service API response with a paginated list of all stores
     */
    public function getAllForAdmin(): ServiceApi;

    /**
     * Retrieve a single store's full details for admin review.
     *
     * @param  string  $id  The store UUID
     * @return ServiceApi The service API response with the full store resource
     */
    public function showForAdmin(string $id): ServiceApi;

    /**
     * Update a store's status (activate, deactivate, suspend).
     *
     * @param  string  $id  The store UUID
     * @param  string  $status  The new status (ACTIVE, INACTIVE, or SUSPENDED)
     * @return ServiceApi The service API response with the updated store resource
     */
    public function updateStatus(string $id, string $status): ServiceApi;
}
