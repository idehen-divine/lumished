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
    public function getStoreForUser(string $userId): ?Store
    {
        return $this->model->where('user_id', $userId)->first();
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
    public function findActive(string $id): ?Store
    {
        return $this->model->active()->where('id', $id)->first();
    }

    /** {@inheritDoc} */
    public function findActiveBySlugOrDomain(?string $slug, ?string $domain): ?Store
    {
        $query = $this->model->active();

        if ($domain) {
            return $query->whereHas('settings', fn ($q) => $q->where('domain', $domain))->first();
        }

        if ($slug) {
            return $query->whereHas('settings', fn ($q) => $q->where('slug', $slug))->first();
        }

        return null;
    }
}
