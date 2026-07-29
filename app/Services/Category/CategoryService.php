<?php

namespace App\Services\Category;

use L0n3ly\LaravelRepositoryWithService\Contracts\BaseService;
use L0n3ly\LaravelRepositoryWithService\Services\ServiceApi;

/**
 * Service interface for category management.
 *
 * Defines the contract for category CRUD operations within a store context.
 * Categories are store-scoped and can be organized as a parent-child tree.
 */
interface CategoryService extends BaseService
{
    /**
     * Retrieve all categories for a store (public).
     *
     * @param  string  $storeSlug  The store slug
     * @return ServiceApi The service API response with a list of categories
     */
    public function getStoreCategories(string $storeSlug): ServiceApi;

    /**
     * Retrieve all categories for the authenticated customer's store.
     *
     * Returns a tree structure with subcategories nested under their parent.
     *
     * @param  string  $storeSlug  The store slug
     * @return ServiceApi The service API response with a tree of categories
     */
    public function getStoreCategoriesForOwner(string $storeSlug): ServiceApi;

    /**
     * Create a new category under a store.
     *
     * Optionally set a parent_id to create a subcategory.
     *
     * @param  string  $storeSlug  The store slug
     * @param  array  $data  The category data (name, description, parent_id)
     * @return ServiceApi The service API response with the created category resource
     */
    public function createCategory(string $storeSlug, array $data): ServiceApi;

    /**
     * Retrieve a single category by its UUID.
     *
     * @param  string  $storeSlug  The store slug
     * @param  string  $id  The category UUID
     * @return ServiceApi The service API response with the category resource
     */
    public function getCategory(string $storeSlug, string $id): ServiceApi;

    /**
     * Update an existing category.
     *
     * A category cannot be its own parent, and the parent must belong to the same store.
     *
     * @param  string  $storeSlug  The store slug
     * @param  string  $id  The category UUID
     * @param  array  $data  The category update data
     * @return ServiceApi The service API response with the updated category resource
     */
    public function updateCategory(string $storeSlug, string $id, array $data): ServiceApi;

    /**
     * Delete a category and reparent its children.
     *
     * Child categories are reparented to the deleted category's parent.
     * Products assigned to this category are unassigned.
     *
     * @param  string  $storeSlug  The store slug
     * @param  string  $id  The category UUID
     * @return ServiceApi The service API response indicating deletion success
     */
    public function deleteCategory(string $storeSlug, string $id): ServiceApi;
}
