<?php

namespace App\Services\Product;

use L0n3ly\LaravelRepositoryWithService\Contracts\BaseService;
use L0n3ly\LaravelRepositoryWithService\Services\ServiceApi;

interface ProductService extends BaseService
{
    /**
     * Get paginated products for the authenticated user's store.
     *
     * @return ServiceApi The service response with paginated products
     */
    public function getStoreProducts(): ServiceApi;

    /**
     * Create a new product under the authenticated user's store.
     *
     * @param  array  $data  The product data (name, description, price, etc.)
     * @return ServiceApi The service response with the created product
     */
    public function createProduct(array $data): ServiceApi;

    /**
     * Get a single product by UUID.
     *
     * @param  string  $id  The product UUID
     * @return ServiceApi The service response with the product
     */
    public function getProduct(string $id): ServiceApi;

    /**
     * Update a product.
     *
     * @param  string  $id  The product UUID
     * @param  array  $data  The product data to update
     * @return ServiceApi The service response with the updated product
     */
    public function updateProduct(string $id, array $data): ServiceApi;

    /**
     * Delete a product.
     *
     * @param  string  $id  The product UUID
     * @return ServiceApi The service response
     */
    public function deleteProduct(string $id): ServiceApi;

    /**
     * Get published products for a store.
     *
     * @param  string  $storeId  The store UUID
     * @return ServiceApi The service response with paginated published products
     */
    public function getPublishedProducts(string $storeId): ServiceApi;

    /**
     * Get published products for public viewing by slug or domain.
     *
     * @param  string|null  $slug  The store slug
     * @param  string|null  $domain  The custom domain
     * @return ServiceApi The service response with paginated published products
     */
    public function getPublishedProductsBySlugOrDomain(?string $slug, ?string $domain): ServiceApi;

    /**
     * Get all products in a store for admin.
     *
     * @param  string  $storeId  The store UUID
     * @return ServiceApi The service response with paginated products
     */
    public function getAdminStoreProducts(string $storeId): ServiceApi;

    /**
     * Delete all products belonging to a store.
     *
     * @param  string  $storeId  The store UUID
     * @return ServiceApi The service response
     */
    public function deleteStoreProducts(string $storeId): ServiceApi;
}
