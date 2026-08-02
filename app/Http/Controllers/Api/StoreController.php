<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Store\CreateStoreRequest;
use App\Http\Requests\Store\StoreLookupRequest;
use App\Http\Requests\Store\UpdateStoreRequest;
use App\Services\Category\CategoryService;
use App\Services\Product\ProductService;
use App\Services\Store\StoreService;
use Illuminate\Http\JsonResponse;

class StoreController extends Controller
{
    public function __construct(
        protected StoreService $storeService,
        protected ProductService $productService,
        protected CategoryService $categoryService,
    ) {}

    /**
     * Get the authenticated customer's store.
     *
     * @group Customer Management
     *
     * @subgroup Store
     *
     * @authenticated
     *
     * @response 200 scenario="Success" {
     *     "code": 200,
     *     "message": null,
     *     "data": {
     *         "store": {
     *             "id": "01953801-abcd-1234-5678-1234567890ab",
     *             "name": "My Store",
     *             "description": "A great store for all your needs.",
     *             "tagline": "Best prices in town",
     *             "logo_url": "https://example.com/logo.png",
     *             "currency": "NGN",
     *             "phone": "+2348012345678",
     *             "email": "store@example.com",
     *             "address": "123 Main Street, Lagos",
     *             "whatsapp_number": "+2348012345678",
     *             "status": "ACTIVE",
     *             "products_count": 25,
     *             "created_at": "2026-07-21 12:00:00"
     *         }
     *     }
     * }
     * @response 401 scenario="Unauthorized" {
     *     "code": 401,
     *     "message": "Unauthorized."
     * }
     * @response 404 scenario="No Store" {
     *     "code": 404,
     *     "message": "You do not have a store yet."
     * }
     * @response 500 scenario="Server Error" {
     *     "code": 500,
     *     "message": "An unexpected error occurred. Please try again later."
     * }
     */
    public function show(): JsonResponse
    {
        return $this->storeService->getUserStore()->toJson();
    }

    /**
     * Create a new store.
     *
     * @group Customer Management
     *
     * @subgroup Store
     *
     * @authenticated
     *
     * @bodyParam name string required The store name. Example: My Store
     * @bodyParam description string required A description of the store. Example: We sell quality goods.
     * @bodyParam address string required The store address. Example: 123 Main Street, Lagos
     * @bodyParam phone_no string required The store phone number. Example: +2348012345678
     * @bodyParam email string The store email address. Example: store@example.com
     * @bodyParam logo image The store logo image.
     *
     * @response 201 scenario="Created" {
     *     "code": 201,
     *     "message": "Store created successfully.",
     *     "data": {
     *         "store": {
     *             "id": "01953801-abcd-1234-5678-1234567890ab",
     *             "name": "My Store",
     *             "description": "A great store for all your needs.",
     *             "tagline": "Best prices in town",
     *             "logo_url": "https://example.com/logo.png",
     *             "currency": "NGN",
     *             "phone": "+2348012345678",
     *             "email": "store@example.com",
     *             "address": "123 Main Street, Lagos",
     *             "whatsapp_number": "+2348012345678",
     *             "status": "ACTIVE",
     *             "products_count": 0,
     *             "created_at": "2026-07-21 15:30:00"
     *         }
     *     }
     * }
     * @response 401 scenario="Unauthorized" {
     *     "code": 401,
     *     "message": "Unauthorized."
     * }
     * @response 422 scenario="Already Has Store" {
     *     "code": 422,
     *     "message": "You already have a store. Only one store per account is allowed."
     * }
     * @response 500 scenario="Server Error" {
     *     "code": 500,
     *     "message": "Failed to create store."
     * }
     */
    public function store(CreateStoreRequest $request): JsonResponse
    {
        return $this->storeService->createStore($request->validated())->toJson();
    }

    /**
     * Update the store.
     *
     * @group Customer Management
     *
     * @subgroup Store
     *
     * @authenticated
     *
     * @response 200 scenario="Success" {
     *     "code": 200,
     *     "message": "Store updated successfully.",
     *     "data": {
     *         "store": {
     *             "id": "01953801-abcd-1234-5678-1234567890ab",
     *             "name": "My Store",
     *             "description": "Updated store description.",
     *             "tagline": "Best prices in town",
     *             "logo_url": "https://example.com/logo.png",
     *             "currency": "NGN",
     *             "phone": "+2348012345678",
     *             "email": "store@example.com",
     *             "address": "123 Main Street, Lagos",
     *             "whatsapp_number": "+2348012345678",
     *             "status": "ACTIVE",
     *             "products_count": 25,
     *             "created_at": "2026-07-21 12:00:00"
     *         }
     *     }
     * }
     * @response 401 scenario="Unauthorized" {
     *     "code": 401,
     *     "message": "Unauthorized."
     * }
     * @response 404 scenario="Not Found" {
     *     "code": 404,
     *     "message": "Store not found."
     * }
     * @response 500 scenario="Server Error" {
     *     "code": 500,
     *     "message": "Failed to update store."
     * }
     */
    public function update(UpdateStoreRequest $request): JsonResponse
    {
        return $this->storeService->updateStore($request->validated())->toJson();
    }

    /**
     * Delete the store.
     *
     * @group Customer Management
     *
     * @subgroup Store
     *
     * @authenticated
     *
     * @response 200 scenario="Success" {
     *     "code": 200,
     *     "message": "Store deleted successfully."
     * }
     * @response 401 scenario="Unauthorized" {
     *     "code": 401,
     *     "message": "Unauthorized."
     * }
     * @response 404 scenario="Not Found" {
     *     "code": 404,
     *     "message": "Store not found."
     * }
     * @response 500 scenario="Server Error" {
     *     "code": 500,
     *     "message": "Failed to delete store."
     * }
     */
    public function destroy(): JsonResponse
    {
        return $this->storeService->deleteStore()->toJson();
    }

    /**
     * Get a store by slug or domain.
     *
     * @group Public
     *
     * @subgroup Stores
     *
     * @unauthenticated
     *
     * @queryParam slug string The store slug. Example: my-store
     * @queryParam domain string The store domain. Example: mystore.com
     *
     * @response 200 scenario=Success {
     *     "code": 200,
     *     "data": {
     *         "store": {
     *             "id": "01953801-abcd-1234-5678-1234567890ab",
     *             "name": "My Store",
     *             "description": "A great store for all your needs.",
     *             "tagline": "Best prices in town",
     *             "logo_url": "https://example.com/logo.png",
     *             "currency": "NGN",
     *             "phone": "+2348012345678",
     *             "email": "store@example.com",
     *             "address": "123 Main Street, Lagos",
     *             "whatsapp_number": "+2348012345678",
     *             "status": "ACTIVE",
     *             "products_count": 25,
     *             "created_at": "2026-07-21 12:00:00"
     *         }
     *     }
     * }
     * @response 404 scenario=NotFound {
     *     "code": 404,
     *     "message": "Store not found."
     * }
     * @response 422 scenario=ValidationError {
     *     "code": 422,
     *     "message": "The given data was invalid.",
     *     "errors": {
     *         "slug": ["Either slug or domain parameter is required."],
     *         "domain": ["Either slug or domain parameter is required."]
     *     }
     * }
     * @response 500 scenario=ServerError {
     *     "code": 500,
     *     "message": "An unexpected error occurred. Please try again later."
     * }
     */
    public function showPublic(StoreLookupRequest $request): JsonResponse
    {
        return $this->storeService->showForPublicBySlugOrDomain(
            $request->query('slug'),
            $request->query('domain')
        )->toJson();
    }

    /**
     * List published products for a store.
     *
     * @group Public
     *
     * @subgroup Stores
     *
     * @unauthenticated
     *
     * @queryParam slug string The store slug. Example: my-store
     * @queryParam domain string The store domain. Example: mystore.com
     * @queryParam page int The page number. Example: 1
     * @queryParam per_page int Items per page (max 100). Example: 10
     * @queryParam search string Search products by name or description. Example: headphones
     * @queryParam sort_by string Sort by any product column. Example: created_at
     * @queryParam sort_order string Sort direction: asc or desc. Example: desc
     *
     * @response 200 scenario=Success {
     *     "code": 200,
     *     "message": "Products retrieved successfully.",
     *     "data": {
     *         "products": [
     *             {
     *                 "id": "01953801-ijkl-9012-3456-1234567890ef",
     *                 "store_id": "01953801-abcd-1234-5678-1234567890ab",
     *                 "name": "Wireless Headphones",
     *                 "description": "High-quality wireless headphones with noise cancellation.",
     *                 "price": "99.99",
     *                 "compare_at_price": "129.99",
     *                 "stock_quantity": 50,
     *                 "photo": "https://example.com/photos/product.jpg",
     *                 "photos": [
     *                     "https://example.com/photos/product-1.jpg",
     *                     "https://example.com/photos/product-2.jpg"
     *                 ],
     *                 "status": "PUBLISHED",
     *                 "categories": [
     *                     {
     *                         "id": "01953801-efgh-5678-9012-1234567890cd",
     *                         "name": "Electronics"
     *                     }
     *                 ],
     *                 "created_at": "2026-07-21 12:00:00"
     *             }
     *         ],
     *         "pagination": {
     *             "current_page": 1,
     *             "last_page": 1,
     *             "per_page": 10,
     *             "total": 1
     *         }
     *     }
     * }
     * @response 404 scenario=NotFound {
     *     "code": 404,
     *     "message": "Store not found."
     * }
     * @response 422 scenario=ValidationError {
     *     "code": 422,
     *     "message": "The given data was invalid.",
     *     "errors": {
     *         "slug": ["Either slug or domain parameter is required."],
     *         "domain": ["Either slug or domain parameter is required."]
     *     }
     * }
     * @response 500 scenario=ServerError {
     *     "code": 500,
     *     "message": "An unexpected error occurred. Please try again later."
     * }
     */
    public function publicProducts(StoreLookupRequest $request): JsonResponse
    {
        return $this->productService->getPublishedProductsBySlugOrDomain(
            $request->query('slug'),
            $request->query('domain')
        )->toJson();
    }

    /**
     * List categories for a store.
     *
     * @group Public
     *
     * @subgroup Stores
     *
     * @unauthenticated
     *
     * @queryParam slug string The store slug. Example: my-store
     * @queryParam domain string The store domain. Example: mystore.com
     *
     * @response 200 scenario=Success {
     *     "code": 200,
     *     "data": {
     *         "categories": [
     *             {
     *                 "id": "01953801-efgh-5678-9012-1234567890cd",
     *                 "store_id": "01953801-abcd-1234-5678-1234567890ab",
     *                 "parent_id": null,
     *                 "name": "Electronics",
     *                 "slug": "electronics",
     *                 "description": "All electronic items",
     *                 "children": [
     *                     {
     *                         "id": "01953801-ijkl-9012-3456-1234567890ef",
     *                         "store_id": "01953801-abcd-1234-5678-1234567890ab",
     *                         "parent_id": "01953801-efgh-5678-9012-1234567890cd",
     *                         "name": "Headphones",
     *                         "slug": "headphones",
     *                         "description": "Headphones and earphones",
     *                         "children": []
     *                     }
     *                 ]
     *             }
     *         ]
     *     }
     * }
     * @response 404 scenario=NotFound {
     *     "code": 404,
     *     "message": "Store not found."
     * }
     * @response 422 scenario=ValidationError {
     *     "code": 422,
     *     "message": "The given data was invalid.",
     *     "errors": {
     *         "slug": ["Either slug or domain parameter is required."],
     *         "domain": ["Either slug or domain parameter is required."]
     *     }
     * }
     * @response 500 scenario=ServerError {
     *     "code": 500,
     *     "message": "An unexpected error occurred. Please try again later."
     * }
     */
    public function publicCategories(StoreLookupRequest $request): JsonResponse
    {
        return $this->categoryService->getStoreCategoriesBySlugOrDomain(
            $request->query('slug'),
            $request->query('domain')
        )->toJson();
    }
}
