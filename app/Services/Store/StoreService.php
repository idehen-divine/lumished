<?php

namespace App\Services\Store;

use L0n3ly\LaravelRepositoryWithService\Contracts\BaseService;
use L0n3ly\LaravelRepositoryWithService\Services\ServiceApi;

interface StoreService extends BaseService
{
    /**
     * Create a new store for the authenticated user.
     *
     * @param  array  $data  The store data (name, description, tagline, currency, etc.)
     * @return ServiceApi The service response with the created store
     */
    public function createStore(array $data): ServiceApi;

    /**
     * Get the authenticated user's store.
     *
     * @return ServiceApi The service response with the user's store
     */
    public function getUserStore(): ServiceApi;

    /**
     * Update the authenticated user's store.
     *
     * @param  array  $data  The store data to update
     * @return ServiceApi The service response with the updated store
     */
    public function updateStore(array $data): ServiceApi;

    /**
     * Delete the authenticated user's store.
     *
     * @return ServiceApi The service response
     */
    public function deleteStore(): ServiceApi;

    /**
     * Get a store's full details for admin review.
     *
     * @param  string  $id  The store UUID
     * @return ServiceApi The service response with the store details
     */
    public function showForAdmin(string $id): ServiceApi;

    /**
     * Get a store for public viewing by slug or domain.
     *
     * Looks up the store using its slug or custom domain.
     *
     * @param  string|null  $slug  The store slug
     * @param  string|null  $domain  The custom domain
     * @return ServiceApi The service response with the public store data
     */
    public function showForPublicBySlugOrDomain(?string $slug, ?string $domain): ServiceApi;

    /**
     * Get all stores for admin listing.
     *
     * @return ServiceApi The service response with paginated stores
     */
    public function getAllForAdmin(): ServiceApi;

    /**
     * Update a store's status.
     *
     * @param  string  $id  The store UUID
     * @param  string  $status  The new status (ACTIVE, INACTIVE, SUSPENDED)
     * @return ServiceApi The service response with the updated store
     */
    public function updateStatus(string $id, string $status): ServiceApi;
}
