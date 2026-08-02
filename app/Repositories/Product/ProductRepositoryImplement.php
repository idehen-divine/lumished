<?php

namespace App\Repositories\Product;

use App\Enums\ProductStatusEnum;
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

        return queryableHelper()->fetchWithFilters($query, [
            'status_column' => 'status',
            'status_map' => [
                'active' => ProductStatusEnum::PUBLISHED->name,
                'inactive' => ProductStatusEnum::DRAFT->name,
            ],
            'searchable' => ['name', 'description'],
        ]);
    }

    /** {@inheritDoc} */
    public function getPublishedProducts(string $storeId): LengthAwarePaginator
    {
        $query = $this->model->published()->ownedByStore($storeId)->with('categories');

        return queryableHelper()->fetchWithFilters($query, [
            'status_column' => 'status',
            'status_map' => [
                'active' => ProductStatusEnum::PUBLISHED->name,
                'inactive' => ProductStatusEnum::DRAFT->name,
            ],
            'searchable' => ['name', 'description'],
        ]);
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
