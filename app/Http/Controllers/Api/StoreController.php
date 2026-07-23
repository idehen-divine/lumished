<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Store\CreateStoreRequest;
use App\Http\Requests\Store\UpdateStoreRequest;
use App\Services\Store\StoreService;
use Illuminate\Http\JsonResponse;

class StoreController extends Controller
{
    public function __construct(protected StoreService $storeService) {}

    /**
     * List the authenticated customer's stores.
     *
     * Retrieves all stores owned by the authenticated customer with pagination.
     *
     * @group Customer Management
     *
     * @subgroup Stores
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
        return $this->storeService->getUserStores()->toJson();
    }

    /**
     * Create a new store.
     *
     * Creates a new store for the authenticated customer. The store slug is auto generated from the name.
     *
     * @group Customer Management
     *
     * @subgroup Stores
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
     *             "products_count": 0,
     *             "created_at": "2026-07-21 15:30:00"
     *         }
     *     }
     * }
     * @response 401 scenario="Unauthorized" {
     *     "code": 401,
     *     "message": "Unauthorized."
     * }
     * @response 422 scenario="Validation Error" {
     *     "message": "The given data was invalid.",
     *     "errors": {
     *         "name": ["The name field is required."]
     *     }
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
     * Get a single store by ID.
     *
     * @group Customer Management
     *
     * @subgroup Stores
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
        return $this->storeService->getStore($id)->toJson();
    }

    /**
     * Update a store.
     *
     * Updates the specified store. Only the owner can update their store.
     *
     * @group Customer Management
     *
     * @subgroup Stores
     *
     * @authenticated
     *
     * @urlParam id string required The store UUID. Example: 01953801-abcd-1234-5678-1234567890ab
     *
     * @response 200 scenario="Success" {
     *     "code": 200,
     *     "message": "Store updated successfully.",
     *     "data": {
     *         "store": {
     *             "id": "01953801-abcd-1234-5678-1234567890ab",
     *             "name": "My Store",
     *             "slug": "my-store",
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
    public function update(UpdateStoreRequest $request, string $id): JsonResponse
    {
        return $this->storeService->updateStore($id, $request->validated())->toJson();
    }

    /**
     * Delete a store.
     *
     * Deletes the specified store and all its associated products and categories.
     *
     * @group Customer Management
     *
     * @subgroup Stores
     *
     * @authenticated
     *
     * @urlParam id string required The store UUID. Example: 01953801-abcd-1234-5678-1234567890ab
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
    public function destroy(string $id): JsonResponse
    {
        return $this->storeService->deleteStore($id)->toJson();
    }
}
