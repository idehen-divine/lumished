<?php

namespace App\Repositories\Product;

use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use L0n3ly\LaravelRepositoryWithService\Contracts\Repository;

interface ProductRepository extends Repository
{
    public function getStoreProducts(string $storeId): LengthAwarePaginator;

    public function getPublishedProducts(string $storeId): LengthAwarePaginator;

    public function findPublished(string $id): ?Product;

    public function findOwnedByStore(string $id, string $storeId): ?Product;

    public function deleteByStore(string $storeId): void;
}
