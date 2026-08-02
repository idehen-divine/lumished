<?php

namespace App\Repositories\Product;

use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use L0n3ly\LaravelRepositoryWithService\Contracts\Repository;

interface ProductRepository extends Repository
{
    /**
     * Get paginated products for a store.
     *
     * @param  string  $storeId  The store UUID
     * @return LengthAwarePaginator Paginated list of products
     */
    public function getStoreProducts(string $storeId): LengthAwarePaginator;

    /**
     * Get paginated published products for a store.
     *
     * @param  string  $storeId  The store UUID
     * @return LengthAwarePaginator Paginated list of published products
     */
    public function getPublishedProducts(string $storeId): LengthAwarePaginator;

    /**
     * Find a product owned by a specific store.
     *
     * @param  string  $id  The product UUID
     * @param  string  $storeId  The store UUID
     * @return Product|null The product or null if not found
     */
    public function findOwnedByStore(string $id, string $storeId): ?Product;

    /**
     * Delete all products belonging to a store.
     *
     * @param  string  $storeId  The store UUID
     */
    public function deleteByStore(string $storeId): void;
}
