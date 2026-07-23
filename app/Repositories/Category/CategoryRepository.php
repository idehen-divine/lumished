<?php

namespace App\Repositories\Category;

use App\Models\Category;
use Illuminate\Support\Collection;
use L0n3ly\LaravelRepositoryWithService\Contracts\Repository;

interface CategoryRepository extends Repository
{
    public function getStoreCategoriesTree(string $storeId): Collection;

    public function findOwnedByStore(string $id, string $storeId): ?Category;

    public function getChildren(string $parentId): Collection;
}
