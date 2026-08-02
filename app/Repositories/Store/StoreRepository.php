<?php

namespace App\Repositories\Store;

use App\Models\Store;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use L0n3ly\LaravelRepositoryWithService\Contracts\Repository;

interface StoreRepository extends Repository
{
    /**
     * Get a store by user ID.
     *
     * @param  string  $userId  The user UUID
     * @return Store|null The store or null if not found
     */
    public function getStoreForUser(string $userId): ?Store;

    /**
     * Get all stores for admin listing with pagination.
     *
     * @return LengthAwarePaginator Paginated list of all stores
     */
    public function getAllForAdmin(): LengthAwarePaginator;

    /**
     * Find an active store by its slug or domain.
     *
     * @param  string|null  $slug  The store slug
     * @param  string|null  $domain  The custom domain
     * @return Store|null The active store or null if not found
     */
    public function findActiveBySlugOrDomain(?string $slug, ?string $domain): ?Store;
}
