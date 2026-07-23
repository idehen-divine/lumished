<?php

namespace App\Services\Product;

use L0n3ly\LaravelRepositoryWithService\Contracts\BaseService;
use L0n3ly\LaravelRepositoryWithService\Services\ServiceApi;

interface ProductService extends BaseService
{
    public function getStoreProducts(string $storeSlug): ServiceApi;

    public function createProduct(string $storeSlug, array $data): ServiceApi;

    public function getProduct(string $storeSlug, string $id): ServiceApi;

    public function updateProduct(string $storeSlug, string $id, array $data): ServiceApi;

    public function deleteProduct(string $storeSlug, string $id): ServiceApi;

    public function getPublishedProducts(string $storeSlug): ServiceApi;

    public function showPublished(string $id): ServiceApi;

    public function getAdminStoreProducts(string $storeSlug): ServiceApi;
}
