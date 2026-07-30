<?php

namespace App\Services\Category;

use App\Enums\ResponseCode;
use App\Http\Resources\CategoryResource;
use App\Repositories\Category\CategoryRepository;
use App\Repositories\Store\StoreRepository;
use App\Traits\LogAndRespond;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use L0n3ly\LaravelRepositoryWithService\Services\ServiceApi;

class CategoryServiceImplement extends ServiceApi implements CategoryService
{
    use LogAndRespond;

    public function __construct(
        protected StoreRepository $storeRepository,
        protected CategoryRepository $categoryRepository,
    ) {}

    private function getUserStore()
    {
        return $this->storeRepository->getStoreForUser(Auth::id());
    }

    /** {@inheritDoc} */
    public function getUserStoreCategories(): ServiceApi
    {
        try {
            $store = $this->getUserStore();

            if (! $store) {
                return $this->setCode(ResponseCode::NOT_FOUND->value)
                    ->setMessage('You do not have a store yet.');
            }

            $categories = $this->categoryRepository->getStoreCategoriesTree($store->id);

            return $this->setCode(ResponseCode::SUCCESS->value)
                ->setData(['categories' => CategoryResource::collection($categories)]);
        } catch (\Throwable $e) {
            return $this->logAndRespond($e);
        }
    }

    /** {@inheritDoc} */
    public function createCategory(array $data): ServiceApi
    {
        try {
            $store = $this->getUserStore();

            if (! $store) {
                return $this->setCode(ResponseCode::NOT_FOUND->value)
                    ->setMessage('You do not have a store yet.');
            }

            $data['store_id'] = $store->id;

            $category = $this->categoryRepository->create($data);

            return $this->setCode(ResponseCode::CREATED->value)
                ->setMessage('Category created successfully.')
                ->setData(['category' => new CategoryResource($category)]);
        } catch (\Throwable $e) {
            return $this->logAndRespond($e, 'Failed to create category.');
        }
    }

    /** {@inheritDoc} */
    public function getCategory(string $id): ServiceApi
    {
        try {
            $store = $this->getUserStore();

            if (! $store) {
                return $this->setCode(ResponseCode::NOT_FOUND->value)
                    ->setMessage('You do not have a store yet.');
            }

            $category = $this->categoryRepository->findOwnedByStore($id, $store->id);

            if (! $category) {
                return $this->setCode(ResponseCode::NOT_FOUND->value)
                    ->setMessage('Category not found.');
            }

            return $this->setCode(ResponseCode::SUCCESS->value)
                ->setData(['category' => new CategoryResource($category)]);
        } catch (\Throwable $e) {
            return $this->logAndRespond($e);
        }
    }

    /** {@inheritDoc} */
    public function updateCategory(string $id, array $data): ServiceApi
    {
        try {
            $store = $this->getUserStore();

            if (! $store) {
                return $this->setCode(ResponseCode::NOT_FOUND->value)
                    ->setMessage('You do not have a store yet.');
            }

            $category = $this->categoryRepository->findOwnedByStore($id, $store->id);

            if (! $category) {
                return $this->setCode(ResponseCode::NOT_FOUND->value)
                    ->setMessage('Category not found.');
            }

            if (isset($data['parent_id']) && $data['parent_id'] === $id) {
                return $this->setCode(ResponseCode::VALIDATION_ERROR->value)
                    ->setMessage('A category cannot be its own parent.');
            }

            if (isset($data['parent_id'])) {
                $parent = $this->categoryRepository->findOwnedByStore($data['parent_id'], $store->id);

                if (! $parent) {
                    return $this->setCode(ResponseCode::VALIDATION_ERROR->value)
                        ->setMessage('The selected parent category does not exist in this store.');
                }
            }

            $this->categoryRepository->update($id, $data);

            $category = $this->categoryRepository->find($id);

            return $this->setCode(ResponseCode::SUCCESS->value)
                ->setMessage('Category updated successfully.')
                ->setData(['category' => new CategoryResource($category)]);
        } catch (\Throwable $e) {
            return $this->logAndRespond($e, 'Failed to update category.');
        }
    }

    /** {@inheritDoc} */
    public function deleteCategory(string $id): ServiceApi
    {
        try {
            $store = $this->getUserStore();

            if (! $store) {
                return $this->setCode(ResponseCode::NOT_FOUND->value)
                    ->setMessage('You do not have a store yet.');
            }

            $category = $this->categoryRepository->findOwnedByStore($id, $store->id);

            if (! $category) {
                return $this->setCode(ResponseCode::NOT_FOUND->value)
                    ->setMessage('Category not found.');
            }

            DB::beginTransaction();

            $parentId = $category->parent_id;

            $this->categoryRepository->getChildren($id)->each(function ($child) use ($parentId) {
                $this->categoryRepository->update($child->id, ['parent_id' => $parentId]);
            });

            $category->products()->detach();

            $this->categoryRepository->delete($id);

            DB::commit();

            return $this->setCode(ResponseCode::SUCCESS->value)
                ->setMessage('Category deleted successfully.');
        } catch (\Throwable $e) {
            DB::rollBack();

            return $this->logAndRespond($e, 'Failed to delete category.');
        }
    }

    /** {@inheritDoc} */
    public function getStoreCategories(string $storeId): ServiceApi
    {
        try {
            $categories = $this->categoryRepository->getStoreCategoriesTree($storeId);

            return $this->setCode(ResponseCode::SUCCESS->value)
                ->setData(['categories' => CategoryResource::collection($categories)]);
        } catch (\Throwable $e) {
            return $this->logAndRespond($e);
        }
    }

    /** {@inheritDoc} */
    public function getStoreCategoriesBySlugOrDomain(?string $slug, ?string $domain): ServiceApi
    {
        try {
            $store = $this->storeRepository->findActiveBySlugOrDomain($slug, $domain);

            if (! $store) {
                return $this->setCode(ResponseCode::NOT_FOUND->value)
                    ->setMessage('Store not found.');
            }

            return $this->getStoreCategories($store->id);
        } catch (\Throwable $e) {
            return $this->logAndRespond($e);
        }
    }
}
