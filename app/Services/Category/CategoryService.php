<?php

namespace App\Services\Category;

use L0n3ly\LaravelRepositoryWithService\Contracts\BaseService;
use L0n3ly\LaravelRepositoryWithService\Services\ServiceApi;

interface CategoryService extends BaseService
{
    public function getStoreCategories(string $storeSlug): ServiceApi;

    public function getStoreCategoriesForOwner(string $storeSlug): ServiceApi;

    public function createCategory(string $storeSlug, array $data): ServiceApi;

    public function getCategory(string $storeSlug, string $id): ServiceApi;

    public function updateCategory(string $storeSlug, string $id, array $data): ServiceApi;

    public function deleteCategory(string $storeSlug, string $id): ServiceApi;
}
