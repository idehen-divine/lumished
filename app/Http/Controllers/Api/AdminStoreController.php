<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Store\UpdateStoreStatusRequest;
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
     * @response 200 scenario="Success" {
     *     "code": 200,
     *     "message": "Stores retrieved successfully.",
     *     "data": {
     *         "stores": [
     *             {
     *                 "id": "01953801-abcd-1234-5678-1234567890ab",
     *                 "name": "My Store",
     *                 "slug": "my-store",
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
     *             "to": 15,
     *             "total": 45,
     *             "per_page": 15,
     *             "first_page": 1,
     *             "previous_page": null,
     *             "current_page": 1,
     *             "next_page": 2,
     *             "last_page": 3
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
     * @urlParam id string required The store UUID. Example: 01953801-abcd-1234-5678-1234567890ab
     *
     * @response 200 scenario="Success" {
     *     "code": 200,
     *     "message": null,
     *     "data": {
     *         "store": {
     *             "id": "01953801-abcd-1234-5678-1234567890ab",
     *             "name": "My Store",
     *             "slug": "my-store",
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
    public function show(string $id): JsonResponse
    {
        return $this->storeService->showForAdmin($id)->toJson();
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
     * @urlParam id string required The store UUID. Example: 01953801-abcd-1234-5678-1234567890ab
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
     *             "slug": "my-store",
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
    public function updateStatus(UpdateStoreStatusRequest $request, string $id): JsonResponse
    {
        return $this->storeService->updateStatus($id, $request->validated()['status'])->toJson();
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
     * @urlParam storeSlug string required The store slug. Example: my-store
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
     *                 "created_at": "2026-07-21 12:00:00"
     *             }
     *         ],
     *         "pagination": {
     *             "from": 1,
     *             "to": 15,
     *             "total": 45,
     *             "per_page": 15,
     *             "first_page": 1,
     *             "previous_page": null,
     *             "current_page": 1,
     *             "next_page": 2,
     *             "last_page": 3
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
    public function products(string $storeSlug): JsonResponse
    {
        return $this->productService->getAdminStoreProducts($storeSlug)->toJson();
    }
}
