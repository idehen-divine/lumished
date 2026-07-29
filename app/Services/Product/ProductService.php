<?php

namespace App\Services\Product;

use L0n3ly\LaravelRepositoryWithService\Contracts\BaseService;
use L0n3ly\LaravelRepositoryWithService\Services\ServiceApi;

interface ProductService extends BaseService
{
    /**
     * Retrieve a paginated list of products for the authenticated customer's store.
     *
     * @param  string  $storeSlug  The store slug
     * @return ServiceApi The service API response with paginated products
     */
    public function getStoreProducts(string $storeSlug): ServiceApi;

    /**
     * Create a new product under a store.
     *
     * Products default to DRAFT status. Must be assigned to at least one category.
     *
     * @param  string  $storeSlug  The store slug
     * @param  array  $data  The product data (name, description, price, category_ids, etc.)
     * @return ServiceApi The service API response with the created product resource
     */
    public function createProduct(string $storeSlug, array $data): ServiceApi;

    /**
     * Retrieve a single product by its UUID.
     *
     * @param  string  $storeSlug  The store slug
     * @param  string  $id  The product UUID
     * @return ServiceApi The service API response with the product resource
     */
    public function getProduct(string $storeSlug, string $id): ServiceApi;

    /**
     * Update an existing product.
     *
     * @param  string  $storeSlug  The store slug
     * @param  string  $id  The product UUID
     * @param  array  $data  The product update data
     * @return ServiceApi The service API response with the updated product resource
     */
    public function updateProduct(string $storeSlug, string $id, array $data): ServiceApi;

    /**
     * Delete a product.
     *
     * @param  string  $storeSlug  The store slug
     * @param  string  $id  The product UUID
     * @return ServiceApi The service API response indicating deletion success
     */
    public function deleteProduct(string $storeSlug, string $id): ServiceApi;

    /**
     * Retrieve published products for public browsing.
     *
     * @param  string  $storeSlug  The store slug
     * @return ServiceApi The service API response with paginated published products
     */
    public function getPublishedProducts(string $storeSlug): ServiceApi;

    /**
     * Retrieve a single published product by its UUID.
     *
     * @param  string  $id  The product UUID
     * @return ServiceApi The service API response with the published product resource
     */
    public function showPublished(string $id): ServiceApi;

    /**
     * Retrieve all products in a store for admin oversight.
     *
     * Includes products of all statuses (DRAFT, PUBLISHED, ARCHIVED).
     *
     * @param  string  $storeSlug  The store slug
     * @return ServiceApi The service API response with paginated products
     */
    public function getAdminStoreProducts(string $storeSlug): ServiceApi;
}
