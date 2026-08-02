<?php

namespace App\Repositories\Store;

use App\Enums\StoreStatusEnum;
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
    public function getAllForAdmin(): LengthAwarePaginator
    {
        $query = $this->model->query()->withCount('products');

        return queryableHelper()->fetchWithFilters($query, [
            'status_column' => 'status',
            'status_map' => [
                'active' => StoreStatusEnum::ACTIVE->name,
                'inactive' => StoreStatusEnum::INACTIVE->name,
            ],
            'searchable' => ['name', 'description', 'tagline'],
        ]);
    }

    /** {@inheritDoc} */
    public function findActiveBySlugOrDomain(?string $slug, ?string $domain): ?Store
    {
        $query = $this->model->active();

        if ($domain) {
            return $query->where('domain', $domain)->first();
        }

        if ($slug) {
            return $query->where('slug', $slug)->first();
        }

        return null;
    }
}
