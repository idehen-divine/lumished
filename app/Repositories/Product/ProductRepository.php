<?php

namespace App\Repositories\Product;

use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use L0n3ly\LaravelRepositoryWithService\Contracts\Repository;

/**
 * Repository interface for product data access.
 *
 * Defines product-specific queries including status filtering and ownership checks.
 */
interface ProductRepository extends Repository
{
    /**
     * Retrieve paginated products for a store (all statuses).
     *
     * @param  string  $storeId  The store UUID
     * @return LengthAwarePaginator A paginated list of products
     */
    public function getStoreProducts(string $storeId): LengthAwarePaginator;

    /**
     * Retrieve paginated published products for a store.
     *
     * @param  string  $storeId  The store UUID
     * @return LengthAwarePaginator A paginated list of published products
     */
    public function getPublishedProducts(string $storeId): LengthAwarePaginator;

    /**
     * Find a published product by its UUID.
     *
     * @param  string  $id  The product UUID
     * @return Product|null The product model if found and published, otherwise null
     */
    public function findPublished(string $id): ?Product;

    /**
     * Find a product by ID that belongs to a specific store.
     *
     * @param  string  $id  The product UUID
     * @param  string  $storeId  The store UUID
     * @return Product|null The product model if found and owned by the store, otherwise null
     */
    public function findOwnedByStore(string $id, string $storeId): ?Product;

    /**
     * Delete all products belonging to a store.
     *
     * @param  string  $storeId  The store UUID
     */
    public function deleteByStore(string $storeId): void;
}
