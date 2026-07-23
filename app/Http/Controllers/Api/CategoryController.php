<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Store\CreateCategoryRequest;
use App\Http\Requests\Store\UpdateCategoryRequest;
use App\Services\Category\CategoryService;
use Illuminate\Http\JsonResponse;

class CategoryController extends Controller
{
    public function __construct(protected CategoryService $categoryService) {}

    /**
     * List categories for a store.
     *
     * Retrieves all categories belonging to the authenticated customer's store. Returns a tree structure with subcategories nested under their parent.
     *
     * @group Customer Management
     *
     * @subgroup Categories
     *
     * @authenticated
     *
     * @urlParam storeSlug string required The store slug. Example: my-store
     *
     * @response 200 scenario="Success" {
     *     "code": 200,
     *     "message": null,
     *     "data": {
     *         "categories": [
     *             {
     *                 "id": "01953801-efgh-5678-1234-1234567890cd",
     *                 "store_id": "01953801-abcd-1234-5678-1234567890ab",
     *                 "parent_id": null,
     *                 "name": "Electronics",
     *                 "slug": "electronics",
     *                 "description": "Electronic gadgets and accessories",
     *                 "products_count": 15,
     *                 "created_at": "2026-07-21 12:00:00"
     *             }
     *         ]
     *     }
     * }
     * @response 401 scenario="Unauthorized" {
     *     "code": 401,
     *     "message": "Unauthorized."
     * }
     * @response 403 scenario="Forbidden" {
     *     "code": 403,
     *     "message": "Store not found or access denied."
     * }
     * @response 500 scenario="Server Error" {
     *     "code": 500,
     *     "message": "An unexpected error occurred. Please try again later."
     * }
     */
    public function index(string $storeSlug): JsonResponse
    {
        return $this->categoryService->getStoreCategoriesForOwner($storeSlug)->toJson();
    }

    /**
     * Create a new category.
     *
     * Creates a new category under the specified store. Optionally set a parent_id to create a subcategory.
     *
     * @group Customer Management
     *
     * @subgroup Categories
     *
     * @authenticated
     *
     * @urlParam storeSlug string required The store slug. Example: my-store
     *
     * @bodyParam name string required The category name. Example: Electronics
     * @bodyParam description string A description of the category. Example: Electronic gadgets and accessories
     * @bodyParam parent_id string The UUID of the parent category for subcategories. Example: 01953801-abcd-1234-5678-1234567890ab
     *
     * @response 201 scenario="Created" {
     *     "code": 201,
     *     "message": "Category created successfully.",
     *     "data": {
     *         "category": {
     *             "id": "01953801-efgh-5678-1234-1234567890cd",
     *             "store_id": "01953801-abcd-1234-5678-1234567890ab",
     *             "parent_id": null,
     *             "name": "Electronics",
     *             "slug": "electronics",
     *             "description": "Electronic gadgets and accessories",
     *             "products_count": 0,
     *             "created_at": "2026-07-21 15:30:00"
     *         }
     *     }
     * }
     * @response 401 scenario="Unauthorized" {
     *     "code": 401,
     *     "message": "Unauthorized."
     * }
     * @response 403 scenario="Forbidden" {
     *     "code": 403,
     *     "message": "Store not found or access denied."
     * }
     * @response 422 scenario="Validation Error" {
     *     "message": "The given data was invalid.",
     *     "errors": {
     *         "name": ["The name field is required."]
     *     }
     * }
     * @response 500 scenario="Server Error" {
     *     "code": 500,
     *     "message": "Failed to create category."
     * }
     */
    public function store(CreateCategoryRequest $request, string $storeSlug): JsonResponse
    {
        return $this->categoryService->createCategory($storeSlug, $request->validated())->toJson();
    }

    /**
     * Get a single category.
     *
     * @group Customer Management
     *
     * @subgroup Categories
     *
     * @authenticated
     *
     * @urlParam storeSlug string required The store slug. Example: my-store
     * @urlParam id string required The category UUID. Example: 01953801-efgh-5678-1234-1234567890cd
     *
     * @response 200 scenario="Success" {
     *     "code": 200,
     *     "message": null,
     *     "data": {
     *         "category": {
     *             "id": "01953801-efgh-5678-1234-1234567890cd",
     *             "store_id": "01953801-abcd-1234-5678-1234567890ab",
     *             "parent_id": null,
     *             "name": "Electronics",
     *             "slug": "electronics",
     *             "description": "Electronic gadgets and accessories",
     *             "products_count": 15,
     *             "created_at": "2026-07-21 12:00:00"
     *         }
     *     }
     * }
     * @response 401 scenario="Unauthorized" {
     *     "code": 401,
     *     "message": "Unauthorized."
     * }
     * @response 403 scenario="Forbidden" {
     *     "code": 403,
     *     "message": "Store not found or access denied."
     * }
     * @response 404 scenario="Not Found" {
     *     "code": 404,
     *     "message": "Category not found."
     * }
     * @response 500 scenario="Server Error" {
     *     "code": 500,
     *     "message": "An unexpected error occurred. Please try again later."
     * }
     */
    public function show(string $storeSlug, string $id): JsonResponse
    {
        return $this->categoryService->getCategory($storeSlug, $id)->toJson();
    }

    /**
     * Update a category.
     *
     * Updates the category name, description, or parent. A category cannot be its own parent, and the parent must belong to the same store.
     *
     * @group Customer Management
     *
     * @subgroup Categories
     *
     * @authenticated
     *
     * @urlParam storeSlug string required The store slug. Example: my-store
     * @urlParam id string required The category UUID. Example: 01953801-abcd-1234-5678-1234567890ab
     *
     * @response 200 scenario="Success" {
     *     "code": 200,
     *     "message": "Category updated successfully.",
     *     "data": {
     *         "category": {
     *             "id": "01953801-efgh-5678-1234-1234567890cd",
     *             "store_id": "01953801-abcd-1234-5678-1234567890ab",
     *             "parent_id": null,
     *             "name": "Electronics",
     *             "slug": "electronics",
     *             "description": "Updated category description.",
     *             "products_count": 15,
     *             "created_at": "2026-07-21 12:00:00"
     *         }
     *     }
     * }
     * @response 401 scenario="Unauthorized" {
     *     "code": 401,
     *     "message": "Unauthorized."
     * }
     * @response 403 scenario="Forbidden" {
     *     "code": 403,
     *     "message": "Store not found or access denied."
     * }
     * @response 404 scenario="Not Found" {
     *     "code": 404,
     *     "message": "Category not found."
     * }
     * @response 422 scenario="Validation Error" {
     *     "message": "The given data was invalid.",
     *     "errors": {
     *         "parent_id": ["A category cannot be its own parent."]
     *     }
     * }
     * @response 500 scenario="Server Error" {
     *     "code": 500,
     *     "message": "Failed to update category."
     * }
     */
    public function update(UpdateCategoryRequest $request, string $storeSlug, string $id): JsonResponse
    {
        return $this->categoryService->updateCategory($storeSlug, $id, $request->validated())->toJson();
    }

    /**
     * Delete a category.
     *
     * Deletes the category. Child categories are reparented to the deleted category's parent. Products assigned to this category are unassigned.
     *
     * @group Customer Management
     *
     * @subgroup Categories
     *
     * @authenticated
     *
     * @urlParam storeSlug string required The store slug. Example: my-store
     * @urlParam id string required The category UUID. Example: 01953801-abcd-1234-5678-1234567890ab
     *
     * @response 200 scenario="Success" {
     *     "code": 200,
     *     "message": "Category deleted successfully."
     * }
     * @response 401 scenario="Unauthorized" {
     *     "code": 401,
     *     "message": "Unauthorized."
     * }
     * @response 403 scenario="Forbidden" {
     *     "code": 403,
     *     "message": "Store not found or access denied."
     * }
     * @response 404 scenario="Not Found" {
     *     "code": 404,
     *     "message": "Category not found."
     * }
     * @response 500 scenario="Server Error" {
     *     "code": 500,
     *     "message": "Failed to delete category."
     * }
     */
    public function destroy(string $storeSlug, string $id): JsonResponse
    {
        return $this->categoryService->deleteCategory($storeSlug, $id)->toJson();
    }
}
