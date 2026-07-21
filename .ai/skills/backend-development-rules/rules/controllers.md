# Controller Implementation Rules

## Core Structure

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\Auth\AuthService;
use Illuminate\Http\JsonResponse;

class AuthController extends Controller
{
    /**
     * Initialize the controller with auth service dependency.
     *
     * @param  AuthService  $authService  The auth service instance
     */
    public function __construct(protected AuthService $authService) {}

    /**
     * User Login
     *
     * Authenticate a user with email and password. Returns authentication token and user details.
     *
     * @group User Management
     * @subgroup Authentication
     *
     * @bodyParam email string required The user's email address. Example: john.doe@example.com
     * @bodyParam password string required The user's password. Example: password123
     *
     * @response 200 scenario=Success {
     *     "code": 200,
     *     "message": "Login successful",
     *     "data": {
     *         "user": {...},
     *         "token": "2|..."
     *     }
     * }
     *
     * @response 401 scenario=InvalidCredentials {
     *     "code": 401,
     *     "message": "Invalid credentials"
     * }
     */
    public function login(LoginRequest $request): JsonResponse
    {
        return $this->authService->login($request)->toJson();
    }
}
```

## Essential Rules

### 1. Inject Services via Constructor

```php
// ✅ CORRECT
public function __construct(
    protected AuthService $authService,
    protected ProductService $productService
) {}

// ❌ WRONG
public function login(LoginRequest $request)
{
    $service = app(AuthService::class);
    // ...
}
```

### 2. Use Form Requests for Validation

```php
// ✅ CORRECT - Form request handles validation
public function login(LoginRequest $request): JsonResponse
{
    return $this->authService->login($request)->toJson();
}

// ❌ WRONG - Validation in controller
public function login(Request $request): JsonResponse
{
    $validated = $request->validate([
        'email' => 'required|email',
    ]);
    // ...
}
```

### 3. Return Service Response with toJson()

```php
// ✅ CORRECT - Required for Scribe documentation
public function login(LoginRequest $request): JsonResponse
{
    return $this->authService->login($request)->toJson();
}

// ❌ WRONG - Missing toJson()
public function login(LoginRequest $request): JsonResponse
{
    return $this->authService->login($request)->toResponse();
}
```

### 4. Keep Controllers Thin

```php
// ✅ CORRECT - All logic in service
public function store(StoreProductRequest $request): JsonResponse
{
    return $this->productService->create($request)->toJson();
}

// ❌ WRONG - Logic in controller
public function store(StoreProductRequest $request): JsonResponse
{
    $product = Product::create($request->validated());
    // ... more logic
    return response()->json($product);
}
```

### 5. Document All Endpoints with Scribe

Every controller method must include:

1. **Method Description** - What the endpoint does
2. **@group** - Organizing category
3. **@subgroup** - Subcategory
4. **@bodyParam** - All request parameters
5. **@authenticated** - If authentication required
6. **@response** - All possible responses

### 6. Document Response Scenarios

```php
/**
 * @response 200 scenario=Success {...}
 * @response 401 scenario=Unauthenticated {...}
 * @response 403 scenario=Forbidden {...}
 * @response 404 scenario=NotFound {...}
 * @response 422 scenario=ValidationError {...}
 * @response 500 scenario=ServerError {...}
 */
```

## Scribe Documentation Pattern

```php
/**
 * User Login
 *
 * Authenticate a user with email and password. Returns authentication 
 * token and user details.
 *
 * @group User Management
 * @subgroup Authentication
 *
 * @bodyParam email string required The user's email address. Example: john.doe@example.com
 * @bodyParam password string required The user's password. Example: password123
 *
 * @response 200 scenario=Success {
 *     "code": 200,
 *     "message": "Login successful",
 *     "data": {
 *         "user": {
 *             "id": "uuid",
 *             "email": "user@example.com",
 *             "first_name": "John",
 *             "last_name": "Doe"
 *         },
 *         "token": "2|abcd..."
 *     }
 * }
 *
 * @response 401 scenario=InvalidCredentials {
 *     "code": 401,
 *     "message": "The provided credentials are incorrect."
 * }
 *
 * @response 422 scenario=ValidationError {
 *     "message": "The given data was invalid.",
 *     "errors": {
 *         "email": ["The email must be a valid email address."]
 *     }
 * }
 *
 * @response 500 scenario=ServerError {
 *     "code": 500,
 *     "message": "An error occurred during authentication",
 *     "error": "Error message details"
 * }
 */
public function login(LoginRequest $request): JsonResponse
{
    return $this->authService->login($request)->toJson();
}
```

## Common Controller Methods

### Show/Get Single Resource

```php
/**
 * Get User Profile
 *
 * Retrieve the authenticated user's profile information.
 *
 * @group User Management
 * @subgroup Profile
 *
 * @authenticated
 *
 * @response 200 scenario=Success {...}
 * @response 401 scenario=Unauthenticated {...}
 */
public function show(string $id): JsonResponse
{
    return $this->userService->show($id)->toJson();
}
```

### List Resources

```php
/**
 * List Products
 *
 * Retrieve paginated list of products with filtering and searching.
 *
 * @group Products
 * @subgroup Management
 *
 * @queryParam page integer The page number. Example: 1
 * @queryParam per_page integer Items per page. Example: 15
 * @queryParam search string Search products by name or SKU. Example: laptop
 * @queryParam status string Filter by status. Example: ACTIVE
 *
 * @response 200 scenario=Success {...}
 */
public function index(): JsonResponse
{
    return $this->productService->getAll()->toJson();
}
```

### Store/Create Resource

```php
/**
 * Create Product
 *
 * Create a new product with details and pricing.
 *
 * @group Products
 * @subgroup Management
 *
 * @bodyParam name string required Product name. Example: Laptop Pro
 * @bodyParam sku string required Unique SKU. Example: LP-2024-001
 * @bodyParam price decimal required Price in dollars. Example: 999.99
 *
 * @response 201 scenario=Created {...}
 * @response 422 scenario=ValidationError {...}
 */
public function store(StoreProductRequest $request): JsonResponse
{
    return $this->productService->create($request)->toJson();
}
```

### Update Resource

```php
/**
 * Update Product
 *
 * Update product details and pricing.
 *
 * @group Products
 * @subgroup Management
 *
 * @urlParam id string required The product ID. Example: 550e8400-e29b-41d4-a716-446655440000
 * @bodyParam name string Product name. Example: Laptop Pro Max
 * @bodyParam price decimal Price in dollars. Example: 1299.99
 *
 * @response 200 scenario=Updated {...}
 * @response 404 scenario=NotFound {...}
 * @response 422 scenario=ValidationError {...}
 */
public function update(UpdateProductRequest $request, string $id): JsonResponse
{
    return $this->productService->update($id, $request)->toJson();
}
```

### Delete Resource

```php
/**
 * Delete Product
 *
 * Delete a product from the system.
 *
 * @group Products
 * @subgroup Management
 *
 * @urlParam id string required The product ID. Example: 550e8400-e29b-41d4-a716-446655440000
 *
 * @response 200 scenario=Deleted {...}
 * @response 404 scenario=NotFound {...}
 */
public function destroy(string $id): JsonResponse
{
    return $this->productService->delete($id)->toJson();
}
```

## Code Quality Rules

1. **No inline comments** - Use docblocks
2. **One controller per resource** - `AuthController`, `ProductController`
3. **No business logic** - All in services
4. **Type-hint Form Requests** - For auto-validation
5. **Return JsonResponse** - All endpoints
6. **Document all endpoints** - Scribe tags required
7. **Organize by feature** - Group in directories
8. **Dependency injection only** - No `app()` helper calls
