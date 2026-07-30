<?php

namespace App\Services\Category;

use L0n3ly\LaravelRepositoryWithService\Contracts\BaseService;
use L0n3ly\LaravelRepositoryWithService\Services\ServiceApi;

interface CategoryService extends BaseService
{
    /**
     * Get the category tree for the authenticated user's store.
     *
     * @return ServiceApi The service response with the category tree
     */
    public function getUserStoreCategories(): ServiceApi;

    /**
     * Create a new category under the authenticated user's store.
     *
     * @param  array  $data  The category data (name, description, parent_id)
     * @return ServiceApi The service response with the created category
     */
    public function createCategory(array $data): ServiceApi;

    /**
     * Get a single category by UUID.
     *
     * @param  string  $id  The category UUID
     * @return ServiceApi The service response with the category
     */
    public function getCategory(string $id): ServiceApi;

    /**
     * Update a category.
     *
     * @param  string  $id  The category UUID
     * @param  array  $data  The category data to update
     * @return ServiceApi The service response with the updated category
     */
    public function updateCategory(string $id, array $data): ServiceApi;

    /**
     * Delete a category.
     *
     * Child categories are reparented to the deleted category's parent.
     *
     * @param  string  $id  The category UUID
     * @return ServiceApi The service response
     */
    public function deleteCategory(string $id): ServiceApi;

    /**
     * Get the category tree for a given store.
     *
     * @param  string  $storeId  The store UUID
     * @return ServiceApi The service response with the category tree
     */
    public function getStoreCategories(string $storeId): ServiceApi;

    /**
     * Get the category tree for public viewing by slug or domain.
     *
     * @param  string|null  $slug  The store slug
     * @param  string|null  $domain  The custom domain
     * @return ServiceApi The service response with the category tree
     */
    public function getStoreCategoriesBySlugOrDomain(?string $slug, ?string $domain): ServiceApi;
}
