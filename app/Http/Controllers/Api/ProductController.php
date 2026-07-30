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
     * List products for the store.
     *
     * Retrieves a paginated list of products belonging to the authenticated customer store.
     *
     * @group Customer Management
     *
     * @subgroup Products
     *
     * @authenticated
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
     *             "current_page": 1,
     *             "last_page": 3,
     *             "per_page": 15,
     *             "total": 45
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
        return $this->productService->getStoreProducts()->toJson();
    }

    /**
     * Create a new product.
     *
     * Creates a new product under the authenticated customer store. Products default to DRAFT status.
     *
     * @group Customer Management
     *
     * @subgroup Products
     *
     * @authenticated
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
     *                     "id": "01953801-efgh-5678-9012-1234567890cd",
     *                     "store_id": "01953801-abcd-1234-5678-1234567890ab",
     *                     "parent_id": null,
     *                     "name": "Electronics",
     *                     "slug": "electronics",
     *                     "description": "Electronic gadgets and accessories",
     *                     "children": [],
     *                     "products_count": 0,
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
     * @response 422 scenario="Validation Error" {
     *     "code": 422,
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
    public function store(CreateProductRequest $request): JsonResponse
    {
        return $this->productService->createProduct($request->validated())->toJson();
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
     * @urlParam id string required The product UUID. Example: 01953801-ijkl-9012-3456-1234567890ef
     *
     * @response 200 scenario="Success" {
     *     "code": 200,
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
     *                     "id": "01953801-efgh-5678-9012-1234567890cd",
     *                     "store_id": "01953801-abcd-1234-5678-1234567890ab",
     *                     "parent_id": null,
     *                     "name": "Electronics",
     *                     "slug": "electronics",
     *                     "description": "Electronic gadgets and accessories",
     *                     "children": [],
     *                     "products_count": 15,
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
     * @response 404 scenario="Not Found" {
     *     "code": 404,
     *     "message": "Product not found."
     * }
     * @response 500 scenario="Server Error" {
     *     "code": 500,
     *     "message": "An unexpected error occurred. Please try again later."
     * }
     */
    public function show(string $id): JsonResponse
    {
        return $this->productService->getProduct($id)->toJson();
    }

    /**
     * Update a product.
     *
     * Updates the specified product.
     *
     * @group Customer Management
     *
     * @subgroup Products
     *
     * @authenticated
     *
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
     *                     "id": "01953801-efgh-5678-9012-1234567890cd",
     *                     "store_id": "01953801-abcd-1234-5678-1234567890ab",
     *                     "parent_id": null,
     *                     "name": "Electronics",
     *                     "slug": "electronics",
     *                     "description": "Electronic gadgets and accessories",
     *                     "children": [],
     *                     "products_count": 15,
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
     * @response 404 scenario="Not Found" {
     *     "code": 404,
     *     "message": "Product not found."
     * }
     * @response 500 scenario="Server Error" {
     *     "code": 500,
     *     "message": "Failed to update product."
     * }
     */
    public function update(UpdateProductRequest $request, string $id): JsonResponse
    {
        return $this->productService->updateProduct($id, $request->validated())->toJson();
    }

    /**
     * Delete a product.
     *
     * Deletes the specified product.
     *
     * @group Customer Management
     *
     * @subgroup Products
     *
     * @authenticated
     *
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
     * @response 404 scenario="Not Found" {
     *     "code": 404,
     *     "message": "Product not found."
     * }
     * @response 500 scenario="Server Error" {
     *     "code": 500,
     *     "message": "Failed to delete product."
     * }
     */
    public function destroy(string $id): JsonResponse
    {
        return $this->productService->deleteProduct($id)->toJson();
    }
}
