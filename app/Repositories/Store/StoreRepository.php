<?php

namespace App\Repositories\Store;

use App\Models\Store;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use L0n3ly\LaravelRepositoryWithService\Contracts\Repository;

interface StoreRepository extends Repository
{
    public function findBySlug(string $slug): ?Store;

    public function findOwnedBy(string $id, string $userId): ?Store;

    public function getAllActive(): LengthAwarePaginator;

    public function getAllForAdmin(): LengthAwarePaginator;

    public function getUserStores(string $userId): LengthAwarePaginator;
}
