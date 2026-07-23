<?php

namespace App\Repositories\Product;

use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use L0n3ly\LaravelRepositoryWithService\Implementations\Eloquent;

class ProductRepositoryImplement extends Eloquent implements ProductRepository
{
    public function __construct(Product $model)
    {
        $this->model = $model;
    }

    /** {@inheritDoc} */
    public function getStoreProducts(string $storeId): LengthAwarePaginator
    {
        $query = $this->model->ownedByStore($storeId)->with('categories');

        return helpers()->queryableHelper()->fetchWithFilters($query);
    }

    /** {@inheritDoc} */
    public function getPublishedProducts(string $storeId): LengthAwarePaginator
    {
        $query = $this->model->published()->ownedByStore($storeId)->with('categories');

        return helpers()->queryableHelper()->fetchWithFilters($query);
    }

    /** {@inheritDoc} */
    public function findPublished(string $id): ?Product
    {
        return $this->model->published()->with('store')->find($id);
    }

    /** {@inheritDoc} */
    public function findOwnedByStore(string $id, string $storeId): ?Product
    {
        return $this->model->where('id', $id)->where('store_id', $storeId)->first();
    }

    /** {@inheritDoc} */
    public function deleteByStore(string $storeId): void
    {
        $this->model->where('store_id', $storeId)->delete();
    }
}
