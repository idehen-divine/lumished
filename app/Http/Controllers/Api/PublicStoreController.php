<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Category\CategoryService;
use App\Services\Product\ProductService;
use App\Services\Store\StoreService;
use Illuminate\Http\JsonResponse;

class PublicStoreController extends Controller
{
    public function __construct(
        protected StoreService $storeService,
        protected ProductService $productService,
        protected CategoryService $categoryService,
    ) {}

    /**
     * List all active stores.
     *
     * Retrieves a paginated list of all active stores available for browsing.
     *
     * @group Public
     *
     * @unauthenticated
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
     * @response 500 scenario="Server Error" {
     *     "code": 500,
     *     "message": "An unexpected error occurred. Please try again later."
     * }
     */
    public function index(): JsonResponse
    {
        return $this->storeService->getAllActive()->toJson();
    }

    /**
     * Get a store by slug.
     *
     * @group Public
     *
     * @unauthenticated
     *
     * @urlParam slug string required The store slug. Example: my-store
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
     *             "products_count": 25,
     *             "created_at": "2026-07-21 12:00:00"
     *         }
     *     }
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
    public function show(string $slug): JsonResponse
    {
        return $this->storeService->showBySlug($slug)->toJson();
    }

    /**
     * List published products for a store.
     *
     * Retrieves a paginated list of published products for public browsing.
     *
     * @group Public
     *
     * @unauthenticated
     *
     * @urlParam slug string required The store slug. Example: my-store
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
     * @response 404 scenario="Not Found" {
     *     "code": 404,
     *     "message": "Store not found."
     * }
     * @response 500 scenario="Server Error" {
     *     "code": 500,
     *     "message": "An unexpected error occurred. Please try again later."
     * }
     */
    public function products(string $slug): JsonResponse
    {
        return $this->productService->getPublishedProducts($slug)->toJson();
    }

    /**
     * List categories for a store.
     *
     * Retrieves all categories for a store for public browsing.
     *
     * @group Public
     *
     * @unauthenticated
     *
     * @urlParam slug string required The store slug. Example: my-store
     *
     * @response 200 scenario="Success" {
     *     "code": 200,
     *     "message": null,
     *     "data": {
     *         "categories": [ ... ]
     *     }
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
    public function categories(string $slug): JsonResponse
    {
        return $this->categoryService->getStoreCategories($slug)->toJson();
    }

    /**
     * Get a single published product by ID.
     *
     * @group Public
     *
     * @unauthenticated
     *
     * @urlParam id string required The product UUID. Example: 01953801-abcd-1234-5678-1234567890ab
     *
     * @response 200 scenario="Success" {
     *     "code": 200,
     *     "message": null,
     *     "data": {
     *         "product": {
     *             "id": "01953801-ijkl-9012-3456-1234567890ef",
     *             "store_id": "01953801-abcd-1234-5678-1234567890ab",
     *             "name": "Wireless Headphones",
     *             "description": "High-quality wireless headphones with noise cancellation.",
     *             "price": "99.99",
     *             "compare_at_price": "129.99",
     *             "stock_quantity": 50,
     *             "photo": "https://example.com/photos/product.jpg",
     *             "photos": ["https://example.com/photos/product-1.jpg", "https://example.com/photos/product-2.jpg"],
     *             "status": "PUBLISHED",
     *             "categories": [
     *                 {
     *                     "id": "01953801-efgh-5678-1234-1234567890cd",
     *                     "store_id": "01953801-abcd-1234-5678-1234567890ab",
     *                     "parent_id": null,
     *                     "name": "Electronics",
     *                     "slug": "electronics",
     *                     "description": "Electronic gadgets and accessories",
     *                     "created_at": "2026-07-21 12:00:00"
     *                 }
     *             ],
     *             "created_at": "2026-07-21 12:00:00"
     *         }
     *     }
     * }
     * @response 404 scenario="Not Found" {
     *     "code": 404,
     *     "message": "Product not found."
     * }
     * @response 500 scenario="Server Error" {
     *     "code": 500,
     *     "message": "An unexpected error occurred. Please try again later."
     * }
     */
    public function showProduct(string $id): JsonResponse
    {
        return $this->productService->showPublished($id)->toJson();
    }
}
