# PermissionHelper Implementation Rules

The PermissionHelper provides a centralized way to validate user permissions and throw appropriate exceptions that services can catch and convert to HTTP responses.

⚠️ **CRITICAL: This is the authoritative implementation. Copy it exactly. Never modify without understanding all implications.**

## Core Purpose

- Validate user permissions using enums
- Throw exceptions for unauthorized/unauthenticated access
- Keep permission logic out of services
- Standardize permission validation across the application

## Location & Setup

Helper class extends `L0n3ly\LaravelDynamicHelpers\Helper` and lives in `app/Helpers/`.

## Complete Implementation (EXACT - DO NOT MODIFY)

Copy this implementation exactly into `app/Helpers/PermissionHelper.php`. Do not change, remove, or add code:

```php
<?php

namespace App\Helpers;

use App\Enums\PermissionEnum;
use L0n3ly\LaravelDynamicHelpers\Helper;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class PermissionHelper extends Helper
{
    /**
     * Validate that the authenticated user has the required permission.
     *
     * Checks that the user is authenticated and has the required permission enum.
     * Throws UnauthorizedHttpException if user not authenticated.
     * Throws AccessDeniedHttpException if user lacks the required permission.
     *
     * @param  \App\Enums\PermissionEnum  $permission  Permission enum case required for the action
     * @throws \Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException
     * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
     */
    public function validatePermission(PermissionEnum $permission): void
    {
        $user = auth()->user();

        if (! $user) {
            throw new UnauthorizedHttpException('', 'User not authenticated.');
        }

        if (! $user->hasPermissionTo($permission->name)) {
            throw new AccessDeniedHttpException('Access denied. Missing required permission to perform this action.');
        }
    }
}
```

### Implementation Notes:

**Do NOT modify this code. It is:**
- ✅ Production tested
- ✅ Type-safe (requires PermissionEnum, not string)
- ✅ Uses Spatie's `hasPermissionTo()` method correctly
- ✅ Passes enum `->name` (not `->value`)
- ✅ Detailed error messages for debugging
- ✅ Proper exception types for response mapping

**Key Implementation Details:**

1. **Strict Type Hinting** - Requires `PermissionEnum`, prevents string mistakes
2. **User Authentication Check** - First checks if user is logged in
3. **Permission Enum Name** - Uses `$permission->name` (the enum case name as string)
4. **Spatie Method** - Uses `hasPermissionTo()` from Spatie permission package (not `hasPermission()`)
5. **Detailed Error Message** - Helps developers understand what permission is missing
6. **Two Exception Types** - Throws different exceptions for clear HTTP response mapping:
   - `UnauthorizedHttpException` (401) - User not authenticated
   - `AccessDeniedHttpException` (403) - User lacks required permission

## Core Methods

### validatePermission()

Validates that authenticated user has a specific permission:

```php
/**
 * Validate user has the specified permission.
 *
 * Throws UnauthorizedHttpException if user not authenticated.
 * Throws AccessDeniedHttpException if user lacks the permission.
 *
 * @param  string|\App\Enums\PermissionEnum  $permission  Permission name or enum
 * @throws UnauthorizedHttpException When user not authenticated
 * @throws AccessDeniedHttpException When user lacks permission
 */
public function validatePermission($permission): void
{
    $user = auth()->user();

    if (!$user) {
        throw new UnauthorizedHttpException('', 'User not authenticated');
    }

    // Support both string and enum
    $permissionName = $permission instanceof \BackedEnum
        ? $permission->value
        : $permission;

    if (!$user->hasPermission($permissionName)) {
        throw new AccessDeniedHttpException('Access denied.');
    }
}
```

## Permission Enums

Define permissions as enums for type safety. The helper uses the enum `->name` property (the case name, not the value):

```php
<?php

namespace App\Enums;

enum AdminPanelPermissionEnum
{
    case VIEW_PRODUCTS;
    case CREATE_PRODUCTS;
    case UPDATE_PRODUCTS;
    case DELETE_PRODUCTS;
    
    case VIEW_ORDERS;
    case UPDATE_ORDERS;
    
    case VIEW_USERS;
    case CREATE_USERS;
    case UPDATE_USERS;
    case DELETE_USERS;
}

enum UserPermissionEnum
{
    case VIEW_PROFILE;
    case UPDATE_PROFILE;
    case VIEW_ORDERS;
    case CREATE_ORDERS;
}
```

**Important:** The PermissionHelper uses the enum case name (e.g., `VIEW_PRODUCTS`), not a string value. The enum doesn't need to be backed by a string. The name is what Spatie checks via `hasPermissionTo($permission->name)`.

**Creating permissions in database:**
```php
// In seeder or migration
Permission::create(['name' => 'VIEW_PRODUCTS']);
Permission::create(['name' => 'CREATE_PRODUCTS']);
// etc...
```

When you call:
```php
helpers()->permissionHelper()->validatePermission(AdminPanelPermissionEnum::VIEW_PRODUCTS);
```

It checks if user has permission with name `'VIEW_PRODUCTS'`.

## Usage in Services

### Basic Permission Check

```php
use App\Enums\AdminPanelPermissionEnum;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

public function getAllProducts()
{
    try {
        helpers()->permissionHelper()->validatePermission(
            AdminPanelPermissionEnum::VIEW_PRODUCTS
        );

        $products = $this->mainRepository->getAllProducts();

        return $this->setCode(ResponseCode::SUCCESS->value)
            ->setMessage('Products retrieved successfully.')
            ->setData([
                'products' => ProductResource::collection($products),
                'pagination' => helpers()->queryableHelper()->getPagination($products),
            ]);
    } catch (AccessDeniedHttpException $e) {
        return $this->setCode(ResponseCode::FORBIDDEN->value)
            ->setMessage($e->getMessage());
    } catch (UnauthorizedHttpException $e) {
        return $this->setCode(ResponseCode::UNAUTHORIZED->value)
            ->setMessage($e->getMessage());
    } catch (\Exception $e) {
        return $this->setCode(ResponseCode::SERVER_ERROR->value)
            ->setMessage('An error occurred while retrieving products')
            ->setError($e->getMessage());
    }
}
```

**Key Pattern:**
- Validate permission at start of method using enum (type-safe)
- Catch `UnauthorizedHttpException` for unauthenticated users (401)
- Catch `AccessDeniedHttpException` for permission denied (403)
- Catch general `\Exception` for other errors (500)
- Permission helper provides detailed error messages

### Multiple Permission Checks

```php
public function deleteProduct(string $id)
{
    try {
        helpers()->permissionHelper()->validatePermission(
            AdminPanelPermissionEnum::DELETE_PRODUCTS
        );

        $product = $this->mainRepository->find($id);

        if (!$product) {
            return $this->setCode(ResponseCode::NOT_FOUND->value)
                ->setMessage('Product not found.');
        }

        $this->mainRepository->delete($id);

        return $this->setCode(ResponseCode::SUCCESS->value)
            ->setMessage('Product deleted successfully.');
    } catch (AccessDeniedHttpException $e) {
        return $this->setCode(ResponseCode::FORBIDDEN->value)
            ->setMessage($e->getMessage());
    } catch (UnauthorizedHttpException $e) {
        return $this->setCode(ResponseCode::UNAUTHORIZED->value)
            ->setMessage($e->getMessage());
    } catch (\Exception $e) {
        return $this->setCode(ResponseCode::SERVER_ERROR->value)
            ->setMessage('An error occurred while deleting product')
            ->setError($e->getMessage());
    }
}
```

### Always Use Enum for Type Safety

```php
use App\Enums\AdminPanelPermissionEnum;

public function updateUser(string $id, array $data)
{
    try {
        // Always use enum (not string) for type safety
        helpers()->permissionHelper()->validatePermission(
            AdminPanelPermissionEnum::UPDATE_USERS
        );

        $user = $this->mainRepository->find($id);

        if (!$user) {
            return $this->setCode(ResponseCode::NOT_FOUND->value)
                ->setMessage('User not found.');
        }

        $this->mainRepository->update($id, $data);

        return $this->setCode(ResponseCode::SUCCESS->value)
            ->setMessage('User updated successfully.')
            ->setData(['user' => new UserResource($user)]);
    } catch (AccessDeniedHttpException $e) {
        return $this->setCode(ResponseCode::FORBIDDEN->value)
            ->setMessage($e->getMessage());
    } catch (UnauthorizedHttpException $e) {
        return $this->setCode(ResponseCode::UNAUTHORIZED->value)
            ->setMessage($e->getMessage());
    } catch (\Exception $e) {
        return $this->setCode(ResponseCode::SERVER_ERROR->value)
            ->setError($e->getMessage());
    }
}
```

**Important:** The helper requires `PermissionEnum` type hint. You cannot pass strings. This is intentional—it forces type safety.

## Common Exception Handling Patterns

### Recommended: Separate Exception Handling

```php
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

public function getData()
{
    try {
        helpers()->permissionHelper()->validatePermission(
            AdminPanelPermissionEnum::VIEW_DATA
        );

        // ... rest of logic

        return $this->setCode(ResponseCode::SUCCESS->value)
            ->setData(['data' => $data]);
    } catch (UnauthorizedHttpException $e) {
        // User not authenticated - throw 401
        return $this->setCode(ResponseCode::UNAUTHORIZED->value)
            ->setMessage($e->getMessage());
    } catch (AccessDeniedHttpException $e) {
        // User authenticated but lacks permission - throw 403
        return $this->setCode(ResponseCode::FORBIDDEN->value)
            ->setMessage($e->getMessage());
    } catch (\Exception $e) {
        // Unexpected errors - throw 500
        return $this->setCode(ResponseCode::SERVER_ERROR->value)
            ->setError($e->getMessage());
    }
}
```

**This pattern is clearest:** Each exception type gets its own response code and messaging.

### Alternative: Generic HttpException Handler

```php
use Symfony\Component\HttpKernel\Exception\HttpException;

public function deleteData(string $id)
{
    try {
        helpers()->permissionHelper()->validatePermission(
            AdminPanelPermissionEnum::DELETE_DATA
        );

        // ... deletion logic

        return $this->setCode(ResponseCode::SUCCESS->value)
            ->setMessage('Data deleted successfully.');
    } catch (HttpException $e) {
        // Catches both UnauthorizedHttpException and AccessDeniedHttpException
        $code = $e->getStatusCode() === 401
            ? ResponseCode::UNAUTHORIZED->value
            : ResponseCode::FORBIDDEN->value;

        return $this->setCode($code)
            ->setMessage($e->getMessage());
    } catch (\Exception $e) {
        return $this->setCode(ResponseCode::SERVER_ERROR->value)
            ->setError($e->getMessage());
    }
}
```

**Only use if you need identical handling for both 401 and 403.** Otherwise, use separate catches.

## Response Codes Mapping

The PermissionHelper throws exceptions that map to these HTTP status codes:

| Exception Thrown | HTTP Code | ResponseCode | Meaning |
|------------------|-----------|--------------|---------|
| `UnauthorizedHttpException` | 401 | `UNAUTHORIZED` | User not authenticated (no auth token/session) |
| `AccessDeniedHttpException` | 403 | `FORBIDDEN` | User authenticated but lacks required permission |
| Other exceptions | 500 | `SERVER_ERROR` | Unexpected errors in your logic |

Always catch and map these to the correct response codes in your services.

## Permission Validation Checklist

- [ ] Use permission enum instead of string (type safety)
- [ ] Validate permission at start of service method
- [ ] Catch `AccessDeniedHttpException` separately
- [ ] Catch general `\Exception` for other errors
- [ ] Return `FORBIDDEN` (403) for permission errors
- [ ] Return `UNAUTHORIZED` (401) if user not authenticated
- [ ] Return `SERVER_ERROR` (500) for unexpected errors
- [ ] Provide clear error message to client
- [ ] Document which permission each method requires

## Code Quality Rules

1. **Always use PermissionEnum** - The helper requires enum type, not strings (enforces type safety)
2. **Validate first** - Check permissions at the start of service methods, before data access
3. **Separate exception handling** - Catch `UnauthorizedHttpException` (401) and `AccessDeniedHttpException` (403) separately
4. **Use helper messages** - Call `$e->getMessage()` to use the detailed error messages from helper
5. **Never use in repositories** - Permission checks belong in services only
6. **Document requirements** - PHPDoc should list which permission enum is required
7. **One enum per domain** - Create separate enums for admin, user, etc. (AdminPanelPermissionEnum, UserPermissionEnum)
8. **Match permission names** - Ensure permission enum cases match database permission names exactly
