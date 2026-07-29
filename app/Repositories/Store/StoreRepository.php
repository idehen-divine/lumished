<?php

namespace App\Repositories\Store;

use App\Models\Store;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use L0n3ly\LaravelRepositoryWithService\Contracts\Repository;

/**
 * Repository interface for store data access.
 *
 * Defines store-specific queries beyond the default CRUD provided by BaseRepository.
 */
interface StoreRepository extends Repository
{
    /**
     * Find a store by its slug.
     *
     * @param  string  $slug  The store slug
     * @return Store|null The store model if found, otherwise null
     */
    public function findBySlug(string $slug): ?Store;

    /**
     * Find a store owned by a specific user.
     *
     * @param  string  $id  The store UUID
     * @param  string  $userId  The owner user UUID
     * @return Store|null The store model if found and owned by the user, otherwise null
     */
    public function findOwnedBy(string $id, string $userId): ?Store;

    /**
     * Retrieve all active stores with pagination.
     *
     * @return LengthAwarePaginator A paginated list of active stores
     */
    public function getAllActive(): LengthAwarePaginator;

    /**
     * Retrieve all stores for admin oversight with pagination.
     *
     * @return LengthAwarePaginator A paginated list of all stores
     */
    public function getAllForAdmin(): LengthAwarePaginator;

    /**
     * Retrieve all stores owned by a specific user with pagination.
     *
     * @param  string  $userId  The owner user UUID
     * @return LengthAwarePaginator A paginated list of the user's stores
     */
    public function getUserStores(string $userId): LengthAwarePaginator;
}
