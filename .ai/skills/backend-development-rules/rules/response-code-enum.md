# ResponseCode Enum Implementation Rules

The ResponseCode enum provides type-safe HTTP status codes and default error messages for API responses.

⚠️ **CRITICAL: This is the authoritative implementation. Copy it exactly. Never modify without understanding all implications.**

## Core Purpose

- Define all valid HTTP status codes as type-safe enum cases
- Provide default error messages for each status code
- Enable strict type checking for response codes
- Prevent hardcoding HTTP status codes throughout the application
- Centralize status code definitions for consistent API responses

## Location & Setup

ResponseCode lives in `app/Enums/ResponseCode.php`.

## Complete Implementation (EXACT - DO NOT MODIFY)

Copy this implementation exactly into `app/Enums/ResponseCode.php`. Do not change, remove, or add code:

```php
<?php

namespace App\Enums;

enum ResponseCode: int
{
    case SUCCESS = 200;
    case CREATED = 201;
    case BAD_REQUEST = 400;
    case UNAUTHORIZED = 401;
    case FORBIDDEN = 403;
    case NOT_FOUND = 404;
    case VALIDATION_ERROR = 422;
    case SERVER_ERROR = 500;

    /**
     * Get the default message for this response code.
     *
     * Provides a human-readable default message for each HTTP status code.
     * Override in services when more specific messaging is needed.
     *
     * @return string The default message for this status code
     */
    public function defaultMessage(): string
    {
        return match ($this) {
            self::SUCCESS => 'Success',
            self::CREATED => 'Resource created successfully.',
            self::BAD_REQUEST => 'Bad request: Invalid input data.',
            self::UNAUTHORIZED => 'Unauthorized: Invalid or expired credentials.',
            self::FORBIDDEN => 'Forbidden: You do not have access to this resource.',
            self::NOT_FOUND => 'Resource not found.',
            self::VALIDATION_ERROR => 'Validation failed.',
            self::SERVER_ERROR => 'Internal server error.',
        };
    }
}
```

### Implementation Notes:

**Do NOT modify this code. It is:**
- ✅ Production tested
- ✅ Backed by int (HTTP status codes require specific integers)
- ✅ Includes CREATED (201) for resource creation responses
- ✅ Provides defaultMessage() for standard responses
- ✅ Uses match() expression for type-safe message mapping
- ✅ Each case has a detailed, user-friendly message
- ✅ Integrates with ResultService for response building

**Key Implementation Details:**

1. **Backed by int** - HTTP status codes must map to specific integers (200, 401, etc.)
2. **Complete set** - Covers all standard REST API status codes
3. **defaultMessage() method** - Returns appropriate message for each code
4. **match() expression** - Type-safe message mapping (not if-else chains)
5. **User-friendly messages** - Each message explains the status clearly

## Why ResponseCode is Backed

ResponseCode is an **exception to the non-backed rule** because:

- HTTP status codes require specific integer values
- These values are defined by the HTTP specification
- Clients expect exactly these integers (200, 401, 403, etc.)
- Storing `->value` as the HTTP response code is essential

```php
// ResponseCode uses ->value for HTTP response code
$code = ResponseCode::SUCCESS;
$httpCode = $code->value;  // 200 (integer)
```

Contrast with other enums:

```php
// Non-backed enums use ->name (case name)
$permission = PermissionEnum::VIEW_PRODUCTS;
$name = $permission->name;  // "VIEW_PRODUCTS" (string)

// Backed enums use ->value (backing value)
$status = ResponseCode::SUCCESS;
$code = $status->value;  // 200 (integer)
```

## HTTP Status Codes Reference

| ResponseCode | Value | Meaning | Use Case |
|---|---|---|---|
| `SUCCESS` | 200 | OK | Successful operation, data returned |
| `CREATED` | 201 | Created | New resource created successfully |
| `BAD_REQUEST` | 400 | Bad Request | Invalid input data, validation failed |
| `UNAUTHORIZED` | 401 | Unauthorized | User not authenticated |
| `FORBIDDEN` | 403 | Forbidden | User authenticated but lacks permission |
| `NOT_FOUND` | 404 | Not Found | Resource doesn't exist |
| `VALIDATION_ERROR` | 422 | Unprocessable Entity | Form/request validation failed |
| `SERVER_ERROR` | 500 | Internal Server Error | Unexpected server error |

## Usage in Services

### Basic Response Pattern

```php
use App\Enums\ResponseCode;

public function getProduct(string $id)
{
    try {
        $product = $this->productRepository->find($id);

        if (!$product) {
            return $this->setCode(ResponseCode::NOT_FOUND->value)
                ->setMessage('Product not found.');
        }

        return $this->setCode(ResponseCode::SUCCESS->value)
            ->setData(['product' => new ProductResource($product)]);
    } catch (\Exception $e) {
        return $this->setCode(ResponseCode::SERVER_ERROR->value)
            ->setError($e->getMessage());
    }
}
```

**Pattern:**
- Use `ResponseCode::ENUM->value` to get the integer code
- Pass to `setCode()` method
- Provide descriptive message via `setMessage()` or use `defaultMessage()`

### With Custom Messages

```php
public function createProduct(array $data)
{
    try {
        $product = $this->productRepository->create($data);

        return $this->setCode(ResponseCode::CREATED->value)
            ->setMessage('Product created successfully.')
            ->setData(['product' => new ProductResource($product)]);
    } catch (ValidationException $e) {
        return $this->setCode(ResponseCode::VALIDATION_ERROR->value)
            ->setMessage('Validation failed.')
            ->setError($e->errors());
    } catch (\Exception $e) {
        return $this->setCode(ResponseCode::SERVER_ERROR->value)
            ->setMessage(ResponseCode::SERVER_ERROR->defaultMessage())
            ->setError($e->getMessage());
    }
}
```

### With Default Messages

```php
public function deleteProduct(string $id)
{
    try {
        $product = $this->productRepository->find($id);

        if (!$product) {
            return $this->setCode(ResponseCode::NOT_FOUND->value)
                ->setMessage(ResponseCode::NOT_FOUND->defaultMessage());
        }

        $this->productRepository->delete($id);

        return $this->setCode(ResponseCode::SUCCESS->value)
            ->setMessage('Product deleted successfully.');
    } catch (\Exception $e) {
        return $this->setCode(ResponseCode::SERVER_ERROR->value)
            ->setMessage(ResponseCode::SERVER_ERROR->defaultMessage())
            ->setError($e->getMessage());
    }
}
```

### Permission Exception Handling

```php
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

public function updateProduct(string $id, array $data)
{
    try {
        helpers()->permissionHelper()->validatePermission(
            PermissionEnum::UPDATE_PRODUCTS
        );

        $product = $this->productRepository->find($id);

        if (!$product) {
            return $this->setCode(ResponseCode::NOT_FOUND->value)
                ->setMessage('Product not found.');
        }

        $this->productRepository->update($id, $data);

        return $this->setCode(ResponseCode::SUCCESS->value)
            ->setData(['product' => new ProductResource($product)]);
    } catch (UnauthorizedHttpException $e) {
        return $this->setCode(ResponseCode::UNAUTHORIZED->value)
            ->setMessage($e->getMessage());
    } catch (AccessDeniedHttpException $e) {
        return $this->setCode(ResponseCode::FORBIDDEN->value)
            ->setMessage($e->getMessage());
    } catch (\Exception $e) {
        return $this->setCode(ResponseCode::SERVER_ERROR->value)
            ->setError($e->getMessage());
    }
}
```

## Response JSON Structure

All services using ResponseCode should follow this structure:

```json
{
    "code": 200,
    "message": "Success",
    "data": {
        "product": {
            "id": "...",
            "name": "..."
        }
    }
}
```

```json
{
    "code": 404,
    "message": "Product not found."
}
```

```json
{
    "code": 500,
    "message": "Internal server error.",
    "error": "Exception message"
}
```

## Service Response Chain

The standard pattern for all services:

```php
return $this->setCode(ResponseCode::SUCCESS->value)
    ->setMessage('Operation successful')
    ->setData(['key' => 'value'])
    ->toJson();  // Required for Scribe documentation
```

## Code Quality Rules

1. **Always use ResponseCode enum** - Never hardcode HTTP status codes (200, 401, 403, etc.)
2. **Use ->value for HTTP code** - ResponseCode is backed, so `->value` gives the integer
3. **Match status to situation** - Use correct code for each scenario (201 for creation, 404 for not found)
4. **Provide clear messages** - Use descriptive messages, not generic ones
5. **Use defaultMessage() for standard responses** - When no custom message needed
6. **One ResponseCode per response** - Each response uses exactly one status code
7. **Never add new cases** - All valid HTTP codes are already defined; ask user if you need to add one
8. **Catch specific exceptions first** - UnauthorizedHttpException → UNAUTHORIZED, AccessDeniedHttpException → FORBIDDEN
9. **Always catch general Exception** - Catch all unexpected errors with SERVER_ERROR
10. **Log server errors** - When catching general exceptions, log the full error for debugging

## Common Mistakes to Avoid

### ❌ WRONG - Hardcoded status codes

```php
// DON'T DO THIS
return response()->json(['data' => $data], 200);
```

### ✅ CORRECT - Use ResponseCode enum

```php
// DO THIS
return $this->setCode(ResponseCode::SUCCESS->value)
    ->setData(['data' => $data]);
```

### ❌ WRONG - Using ->name instead of ->value

```php
// DON'T DO THIS (ResponseCode is backed)
$code = ResponseCode::SUCCESS->name;  // "SUCCESS" (string, wrong)
```

### ✅ CORRECT - Using ->value for backed enum

```php
// DO THIS
$code = ResponseCode::SUCCESS->value;  // 200 (integer, correct)
```

### ❌ WRONG - Creating custom status codes

```php
// DON'T DO THIS
enum MyCustomCode: int
{
    case CUSTOM = 418;
}
```

### ✅ CORRECT - Use ResponseCode for all HTTP codes

```php
// DO THIS
ResponseCode::SERVER_ERROR->value;  // 500
```

## Complete Example: Full Service Implementation

```php
<?php

namespace App\Services\Product;

use App\Enums\PermissionEnum;
use App\Enums\ResponseCode;
use App\Http\Resources\ProductResource;
use App\Repositories\Product\ProductRepository;
use L0n3ly\LaravelRepositoryWithService\Traits\ResultService;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class ProductServiceImplement implements ProductService
{
    use ResultService;

    public function __construct(protected ProductRepository $productRepository) {}

    /**
     * Get all products with pagination, filtering, and search.
     */
    public function getAllProducts()
    {
        try {
            helpers()->permissionHelper()->validatePermission(
                PermissionEnum::VIEW_PRODUCTS
            );

            $products = $this->productRepository->getAllProducts();

            return $this->setCode(ResponseCode::SUCCESS->value)
                ->setMessage('Products retrieved successfully.')
                ->setData([
                    'products' => ProductResource::collection($products),
                    'pagination' => helpers()->queryableHelper()->getPagination($products),
                ]);
        } catch (UnauthorizedHttpException $e) {
            return $this->setCode(ResponseCode::UNAUTHORIZED->value)
                ->setMessage($e->getMessage());
        } catch (AccessDeniedHttpException $e) {
            return $this->setCode(ResponseCode::FORBIDDEN->value)
                ->setMessage($e->getMessage());
        } catch (\Exception $e) {
            return $this->setCode(ResponseCode::SERVER_ERROR->value)
                ->setMessage(ResponseCode::SERVER_ERROR->defaultMessage())
                ->setError($e->getMessage());
        }
    }

    /**
     * Create a new product.
     */
    public function createProduct(array $data)
    {
        try {
            helpers()->permissionHelper()->validatePermission(
                PermissionEnum::CREATE_PRODUCTS
            );

            $product = $this->productRepository->create($data);

            return $this->setCode(ResponseCode::CREATED->value)
                ->setMessage('Product created successfully.')
                ->setData(['product' => new ProductResource($product)]);
        } catch (UnauthorizedHttpException $e) {
            return $this->setCode(ResponseCode::UNAUTHORIZED->value)
                ->setMessage($e->getMessage());
        } catch (AccessDeniedHttpException $e) {
            return $this->setCode(ResponseCode::FORBIDDEN->value)
                ->setMessage($e->getMessage());
        } catch (\Exception $e) {
            return $this->setCode(ResponseCode::SERVER_ERROR->value)
                ->setError($e->getMessage());
        }
    }

    /**
     * Get a product by ID.
     */
    public function getProduct(string $id)
    {
        try {
            helpers()->permissionHelper()->validatePermission(
                PermissionEnum::VIEW_PRODUCTS
            );

            $product = $this->productRepository->find($id);

            if (!$product) {
                return $this->setCode(ResponseCode::NOT_FOUND->value)
                    ->setMessage(ResponseCode::NOT_FOUND->defaultMessage());
            }

            return $this->setCode(ResponseCode::SUCCESS->value)
                ->setData(['product' => new ProductResource($product)]);
        } catch (UnauthorizedHttpException $e) {
            return $this->setCode(ResponseCode::UNAUTHORIZED->value)
                ->setMessage($e->getMessage());
        } catch (AccessDeniedHttpException $e) {
            return $this->setCode(ResponseCode::FORBIDDEN->value)
                ->setMessage($e->getMessage());
        } catch (\Exception $e) {
            return $this->setCode(ResponseCode::SERVER_ERROR->value)
                ->setError($e->getMessage());
        }
    }

    /**
     * Delete a product.
     */
    public function deleteProduct(string $id)
    {
        try {
            helpers()->permissionHelper()->validatePermission(
                PermissionEnum::DELETE_PRODUCTS
            );

            $product = $this->productRepository->find($id);

            if (!$product) {
                return $this->setCode(ResponseCode::NOT_FOUND->value)
                    ->setMessage('Product not found.');
            }

            $this->productRepository->delete($id);

            return $this->setCode(ResponseCode::SUCCESS->value)
                ->setMessage('Product deleted successfully.');
        } catch (UnauthorizedHttpException $e) {
            return $this->setCode(ResponseCode::UNAUTHORIZED->value)
                ->setMessage($e->getMessage());
        } catch (AccessDeniedHttpException $e) {
            return $this->setCode(ResponseCode::FORBIDDEN->value)
                ->setMessage($e->getMessage());
        } catch (\Exception $e) {
            return $this->setCode(ResponseCode::SERVER_ERROR->value)
                ->setError($e->getMessage());
        }
    }
}
```

This example demonstrates:
- ✅ Permission validation first
- ✅ Separate exception handling for authorization/forbidden
- ✅ General exception catching for SERVER_ERROR
- ✅ Appropriate ResponseCode for each scenario
- ✅ Using `->value` to get HTTP integer code
- ✅ Providing clear messages to clients
- ✅ Using defaultMessage() for standard responses
