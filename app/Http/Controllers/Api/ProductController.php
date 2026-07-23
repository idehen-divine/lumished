<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Store\CreateProductRequest;
use App\Http\Requests\Store\UpdateProductRequest;
use App\Services\Product\ProductService;
use Illuminate\Http\JsonResponse;

class ProductController extends Controller
{
    public function __construct(protected ProductService $productService) {}

    /**
     * List products for a store.
     *
     * Retrieves a paginated list of products belonging to the authenticated customer's store.
     *
     * @group Customer Management
     *
     * @subgroup Products
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
        return $this->productService->getStoreProducts($storeSlug)->toJson();
    }

    /**
     * Create a new product.
     *
     * Creates a new product under the specified store. Products default to DRAFT status. Must be assigned to at least one category.
     *
     * @group Customer Management
     *
     * @subgroup Products
     *
     * @authenticated
     *
     * @urlParam storeSlug string required The store slug. Example: my-store
     *
     * @bodyParam name string required The product name. Example: Wireless Headphones
     * @bodyParam description string required Product description. Example: High quality wireless headphones with noise cancellation.
     * @bodyParam price number required The product price. Example: 99.99
     * @bodyParam compare_at_price number The original price for comparison. Example: 149.99
     * @bodyParam currency string required The currency code. Example: NGN
     * @bodyParam quantity int required Available stock quantity. Example: 50
     * @bodyParam photos array Product images.
     * @bodyParam category_ids array required Array of category UUIDs. Example: ["01953801-abcd-1234-5678-1234567890ab"]
     *
     * @response 201 scenario="Created" {
     *     "code": 201,
     *     "message": "Product created successfully.",
     *     "data": {
     *         "product": {
     *             "id": "01953801-ijkl-9012-3456-1234567890ef",
     *             "store_id": "01953801-abcd-1234-5678-1234567890ab",
     *             "name": "Wireless Headphones",
     *             "description": "High-quality wireless headphones with noise cancellation.",
     *             "price": "99.99",
     *             "compare_at_price": null,
     *             "stock_quantity": 50,
     *             "photo": "https://example.com/photos/product.jpg",
     *             "photos": ["https://example.com/photos/product-1.jpg", "https://example.com/photos/product-2.jpg"],
     *             "status": "DRAFT",
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
     *     "message": "Failed to create product."
     * }
     */
    public function store(CreateProductRequest $request, string $storeSlug): JsonResponse
    {
        return $this->productService->createProduct($storeSlug, $request->validated())->toJson();
    }

    /**
     * Get a single product.
     *
     * @group Customer Management
     *
     * @subgroup Products
     *
     * @authenticated
     *
     * @urlParam storeSlug string required The store slug. Example: my-store
     * @urlParam id string required The product UUID. Example: 01953801-ijkl-9012-3456-1234567890ef
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
     *     "message": "Product not found."
     * }
     * @response 500 scenario="Server Error" {
     *     "code": 500,
     *     "message": "An unexpected error occurred. Please try again later."
     * }
     */
    public function show(string $storeSlug, string $id): JsonResponse
    {
        return $this->productService->getProduct($storeSlug, $id)->toJson();
    }

    /**
     * Update a product.
     *
     * Updates the specified product. Only the store owner can update products.
     *
     * @group Customer Management
     *
     * @subgroup Products
     *
     * @authenticated
     *
     * @urlParam storeSlug string required The store slug. Example: my-store
     * @urlParam id string required The product UUID. Example: 01953801-abcd-1234-5678-1234567890ab
     *
     * @response 200 scenario="Success" {
     *     "code": 200,
     *     "message": "Product updated successfully.",
     *     "data": {
     *         "product": {
     *             "id": "01953801-ijkl-9012-3456-1234567890ef",
     *             "store_id": "01953801-abcd-1234-5678-1234567890ab",
     *             "name": "Wireless Headphones",
     *             "description": "Updated product description.",
     *             "price": "79.99",
     *             "compare_at_price": "99.99",
     *             "stock_quantity": 45,
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
     *     "message": "Product not found."
     * }
     * @response 500 scenario="Server Error" {
     *     "code": 500,
     *     "message": "Failed to update product."
     * }
     */
    public function update(UpdateProductRequest $request, string $storeSlug, string $id): JsonResponse
    {
        return $this->productService->updateProduct($storeSlug, $id, $request->validated())->toJson();
    }

    /**
     * Delete a product.
     *
     * Deletes the specified product. Only the store owner can delete products.
     *
     * @group Customer Management
     *
     * @subgroup Products
     *
     * @authenticated
     *
     * @urlParam storeSlug string required The store slug. Example: my-store
     * @urlParam id string required The product UUID. Example: 01953801-abcd-1234-5678-1234567890ab
     *
     * @response 200 scenario="Success" {
     *     "code": 200,
     *     "message": "Product deleted successfully."
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
     *     "message": "Product not found."
     * }
     * @response 500 scenario="Server Error" {
     *     "code": 500,
     *     "message": "Failed to delete product."
     * }
     */
    public function destroy(string $storeSlug, string $id): JsonResponse
    {
        return $this->productService->deleteProduct($storeSlug, $id)->toJson();
    }
}
