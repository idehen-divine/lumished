<?php

namespace App\Repositories\Category;

use App\Models\Category;
use Illuminate\Support\Collection;
use L0n3ly\LaravelRepositoryWithService\Implementations\Eloquent;

class CategoryRepositoryImplement extends Eloquent implements CategoryRepository
{
    public function __construct(Category $model)
    {
        $this->model = $model;
    }

    /** {@inheritDoc} */
    public function getStoreCategoriesTree(string $storeId): Collection
    {
        return $this->model
            ->where('store_id', $storeId)
            ->with('children')
            ->whereNull('parent_id')
            ->get();
    }

    /** {@inheritDoc} */
    public function findOwnedByStore(string $id, string $storeId): ?Category
    {
        return $this->model->where('id', $id)->where('store_id', $storeId)->first();
    }

    /** {@inheritDoc} */
    public function getChildren(string $parentId): Collection
    {
        return $this->model->where('parent_id', $parentId)->get();
    }
}
