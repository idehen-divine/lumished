<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Store\UpdateStoreStatusRequest;
use App\Models\Store;
use App\Services\Product\ProductService;
use App\Services\Store\StoreService;
use Illuminate\Http\JsonResponse;

class AdminStoreController extends Controller
{
    public function __construct(
        protected StoreService $storeService,
        protected ProductService $productService,
    ) {}

    /**
     * List all stores for admin.
     *
     * Retrieves a paginated list of all stores in the system for admin oversight.
     *
     * @group Admin Management
     *
     * @subgroup Store Management
     *
     * @authenticated
     *
     * @queryParam page int The page number. Example: 1
     * @queryParam per_page int Items per page (max 100). Example: 10
     * @queryParam search string Search stores by name, description, or tagline. Example: electronics
     * @queryParam status string Filter by status: active (ACTIVE) or inactive (INACTIVE). Example: active
     * @queryParam sort_by string Sort by any store column. Example: created_at
     * @queryParam sort_order string Sort direction: asc or desc. Example: desc
     *
     * @response 200 scenario="Success" {
     *     "code": 200,
     *     "message": "Stores retrieved successfully.",
     *     "data": {
     *         "stores": [
     *             {
     *                 "id": "01953801-abcd-1234-5678-1234567890ab",
     *                 "name": "My Store",
     *                 "description": "A great store for all your needs.",
     *                 "tagline": "Best prices in town",
     *                 "logo_url": "https://example.com/logo.png",
     *                 "currency": "NGN",
     *                 "phone": "+2348012345678",
     *                 "email": "store@example.com",
     *                 "address": "123 Main Street, Lagos",
     *                 "whatsapp_number": "+2348012345678",
     *                 "status": "ACTIVE",
     *                 "products_count": 25,
     *                 "created_at": "2026-07-21 12:00:00"
     *             }
     *         ],
     *         "pagination": {
     *             "from": 1,
     *             "to": 10,
     *             "total": 45,
     *             "per_page": 10,
     *             "first_page": 1,
     *             "previous_page": null,
     *             "current_page": 1,
     *             "next_page": 2,
     *             "last_page": 5
     *         }
     *     }
     * }
     * @response 401 scenario="Unauthorized" {
     *     "code": 401,
     *     "message": "Unauthorized."
     * }
     * @response 500 scenario="Server Error" {
     *     "code": 500,
     *     "message": "An unexpected error occurred. Please try again later."
     * }
     */
    public function index(): JsonResponse
    {
        return $this->storeService->getAllForAdmin()->toJson();
    }

    /**
     * Get a store's full details for admin review.
     *
     * @group Admin Management
     *
     * @subgroup Store Management
     *
     * @authenticated
     *
     * @urlParam store string required The store UUID. Example: 01953801-abcd-1234-5678-1234567890ab
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
     * @response 404 scenario="Not Found" {
     *     "code": 404,
     *     "message": "Store not found."
     * }
     * @response 500 scenario="Server Error" {
     *     "code": 500,
     *     "message": "An unexpected error occurred. Please try again later."
     * }
     */
    public function show(Store $store): JsonResponse
    {
        return $this->storeService->showForAdmin($store->id)->toJson();
    }

    /**
     * Update a store's status.
     *
     * Changes the store's status to active, inactive, or suspended.
     *
     * @group Admin Management
     *
     * @subgroup Store Management
     *
     * @authenticated
     *
     * @urlParam store string required The store UUID. Example: 01953801-abcd-1234-5678-1234567890ab
     *
     * @bodyParam status string required The new status. Must be ACTIVE, INACTIVE, or SUSPENDED. Example: ACTIVE
     *
     * @response 200 scenario="Success" {
     *     "code": 200,
     *     "message": "Store status updated successfully.",
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
     *             "status": "SUSPENDED",
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
     * @response 422 scenario="Validation Error" {
     *     "code": 422,
     *     "message": "The given data was invalid.",
     *     "errors": {
     *         "status": ["The selected status is invalid."]
     *     }
     * }
     * @response 500 scenario="Server Error" {
     *     "code": 500,
     *     "message": "Failed to update store status."
     * }
     */
    public function updateStatus(UpdateStoreStatusRequest $request, Store $store): JsonResponse
    {
        return $this->storeService->updateStatus($store->id, $request->validated()['status'])->toJson();
    }

    /**
     * List all products in a store for admin.
     *
     * Retrieves all products in a store including unpublished and archived ones.
     *
     * @group Admin Management
     *
     * @subgroup Store Management
     *
     * @authenticated
     *
     * @urlParam store string required The store UUID. Example: 01953801-abcd-1234-5678-1234567890ab
     *
     * @queryParam page int The page number. Example: 1
     * @queryParam per_page int Items per page (max 100). Example: 10
     * @queryParam search string Search products by name or description. Example: headphones
     * @queryParam status string Filter by status: active (PUBLISHED) or inactive (DRAFT). Example: active
     * @queryParam sort_by string Sort by any product column. Example: created_at
     * @queryParam sort_order string Sort direction: asc or desc. Example: desc
     *
     * @response 200 scenario="Success" {
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
     *                 "photos": ["https://example.com/photos/product-1.jpg", "https://example.com/photos/product-2.jpg"],
     *                 "status": "PUBLISHED",
     *                 "categories": [
     *                     {
     *                         "id": "01953801-efgh-5678-9012-1234567890cd",
     *                         "store_id": "01953801-abcd-1234-5678-1234567890ab",
     *                         "parent_id": null,
     *                         "name": "Electronics",
     *                         "slug": "electronics",
     *                         "description": "Electronic gadgets and accessories",
     *                         "children": [],
     *                         "products_count": 15,
     *                         "created_at": "2026-07-21 12:00:00"
     *                     }
     *                 ],
     *                 "created_at": "2026-07-21 12:00:00"
     *             }
     *         ],
     *         "pagination": {
     *             "from": 1,
     *             "to": 10,
     *             "total": 45,
     *             "per_page": 10,
     *             "first_page": 1,
     *             "previous_page": null,
     *             "current_page": 1,
     *             "next_page": 2,
     *             "last_page": 5
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
     *     "message": "An unexpected error occurred. Please try again later."
     * }
     */
    public function products(Store $store): JsonResponse
    {
        return $this->productService->getAdminStoreProducts($store->id)->toJson();
    }
}
