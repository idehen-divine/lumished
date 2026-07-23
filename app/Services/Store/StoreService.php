<?php

namespace App\Services\Store;

use L0n3ly\LaravelRepositoryWithService\Contracts\BaseService;
use L0n3ly\LaravelRepositoryWithService\Services\ServiceApi;

interface StoreService extends BaseService
{
    public function createStore(array $data): ServiceApi;

    public function getUserStores(): ServiceApi;

    public function getStore(string $id): ServiceApi;

    public function updateStore(string $id, array $data): ServiceApi;

    public function deleteStore(string $id): ServiceApi;

    public function getAllActive(): ServiceApi;

    public function showBySlug(string $slug): ServiceApi;

    public function getAllForAdmin(): ServiceApi;

    public function showForAdmin(string $id): ServiceApi;

    public function updateStatus(string $id, string $status): ServiceApi;
}
