<?php

namespace App\Repositories\Category;

use App\Models\Category;
use Illuminate\Support\Collection;
use L0n3ly\LaravelRepositoryWithService\Contracts\Repository;

/**
 * Repository interface for category data access.
 *
 * Defines category-specific queries including tree traversal and ownership checks.
 */
interface CategoryRepository extends Repository
{
    /**
     * Retrieve all categories for a store as a tree structure.
     *
     * Categories are ordered with parents first, then children nested under them.
     *
     * @param  string  $storeId  The store UUID
     * @return Collection A collection of categories with children loaded
     */
    public function getStoreCategoriesTree(string $storeId): Collection;

    /**
     * Find a category by ID that belongs to a specific store.
     *
     * @param  string  $id  The category UUID
     * @param  string  $storeId  The store UUID
     * @return Category|null The category model if found and owned by the store, otherwise null
     */
    public function findOwnedByStore(string $id, string $storeId): ?Category;

    /**
     * Retrieve all child categories for a given parent.
     *
     * @param  string  $parentId  The parent category UUID
     * @return Collection A collection of child categories
     */
    public function getChildren(string $parentId): Collection;
}
