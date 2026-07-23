<?php

namespace App\Repositories\Store;

use App\Models\Store;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use L0n3ly\LaravelRepositoryWithService\Implementations\Eloquent;

class StoreRepositoryImplement extends Eloquent implements StoreRepository
{
    public function __construct(Store $model)
    {
        $this->model = $model;
    }

    /** {@inheritDoc} */
    public function findBySlug(string $slug): ?Store
    {
        return $this->model->where('slug', $slug)->first();
    }

    /** {@inheritDoc} */
    public function findOwnedBy(string $id, string $userId): ?Store
    {
        return $this->model->where('id', $id)->where('user_id', $userId)->first();
    }

    /** {@inheritDoc} */
    public function getAllActive(): LengthAwarePaginator
    {
        $query = $this->model->active()->withCount('products');

        return helpers()->queryableHelper()->fetchWithFilters($query);
    }

    /** {@inheritDoc} */
    public function getAllForAdmin(): LengthAwarePaginator
    {
        $query = $this->model->query()->withCount('products');

        return helpers()->queryableHelper()->fetchWithFilters($query);
    }

    /** {@inheritDoc} */
    public function getUserStores(string $userId): LengthAwarePaginator
    {
        $query = $this->model->ownedBy($userId);

        return helpers()->queryableHelper()->fetchWithFilters($query);
    }
}
