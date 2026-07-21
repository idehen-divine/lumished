# Service Implementation Rules

## Interface Definition

**Rule:** Service interfaces define contracts with type hints and PHPDoc. Implementations duplicate full documentation for reliable IDE support.

```php
<?php

namespace App\Services\Auth;

use L0n3ly\LaravelRepositoryWithService\Contracts\BaseService;
use L0n3ly\LaravelRepositoryWithService\Services\ServiceApi;

interface AuthService extends BaseService
{
    /**
     * Authenticate a user with email and password.
     *
     * Validates user credentials, checks account status, revokes existing tokens,
     * and generates a new authentication token for API access.
     *
     * @param  array  $credentials  The login credentials with email and password
     * @param  string  $deviceName  The device name for token identification
     * @return ServiceApi Returns service response with user data and access token
     */
    public function login(array $credentials, string $deviceName): ServiceApi;
}
```

## Core Implementation

```php
<?php

namespace App\Services\Auth;

use App\Enums\ResponseCode;
use App\Http\Resources\UserResource;
use App\Repositories\User\UserRepository;
use Illuminate\Support\Facades\Hash;
use L0n3ly\LaravelRepositoryWithService\Services\ServiceApi;

class AuthServiceImplement extends ServiceApi implements AuthService
{
    /**
     * Initialize the service with user repository dependency.
     *
     * @param  UserRepository  $userRepository  The user repository instance
     */
    public function __construct(protected UserRepository $userRepository) {}

    /**
     * Authenticate a user with email and password.
     *
     * Validates user credentials, checks account status, revokes existing tokens,
     * and generates a new authentication token for API access.
     *
     * @param  array  $credentials  The login credentials with email and password
     * @param  string  $deviceName  The device name for token identification
     * @return ServiceApi Returns service response with user data and access token
     */
    public function login(array $credentials, string $deviceName): ServiceApi
    {
        try {
            $user = $this->userRepository->findByEmail($credentials['email']);

            if (!$user || !Hash::check($credentials['password'], $user->password)) {
                return $this->setCode(ResponseCode::UNAUTHORIZED->value)
                    ->setMessage('The provided credentials are incorrect.');
            }

            $user->tokens()->delete();
            $token = $user->createToken($deviceName)->plainTextToken;

            return $this->setCode(ResponseCode::SUCCESS->value)
                ->setMessage('User authenticated successfully')
                ->setData([
                    'user' => new UserResource($user),
                    'token' => $token,
                ]);
        } catch (\Exception $e) {
            return $this->setCode(ResponseCode::SERVER_ERROR->value)
                ->setMessage('An error occurred during authentication')
                ->setError($e->getMessage());
        }
    }
}
```

## Extension Rules

1. **Extend `ServiceApi`** - Provides response methods
2. **Implement interface** - Must match interface methods
3. **Inject repositories** - Via constructor dependency injection
4. **Wrap in try-catch** - Always handle exceptions

## Response Methods

```php
setCode(int $code)              // Set HTTP status code
setMessage(string $message)     // Set response message
setData(array $data)            // Set response data (must be array)
setError(string $error)         // Set error details
toJson()                        // Convert to JSON (required for Scribe)
toResponse()                    // Convert to JsonResponse
```

## ResponseCode Enum Usage

```php
use App\Enums\ResponseCode;

// ✅ CORRECT
return $this->setCode(ResponseCode::SUCCESS->value)
    ->setMessage('Operation successful')
    ->setData(['key' => 'value']);

// ❌ WRONG
return $this->setCode(200)->setMessage('Success');
```

## Available Response Codes

```php
ResponseCode::CREATED = 201              // Resource created
ResponseCode::SUCCESS = 200              // Operation successful
ResponseCode::BAD_REQUEST = 400          // Invalid request data
ResponseCode::UNAUTHORIZED = 401         // Authentication required
ResponseCode::FORBIDDEN = 403            // Permission denied
ResponseCode::NOT_FOUND = 404            // Resource not found
ResponseCode::VALIDATION_ERROR = 422     // Validation failed
ResponseCode::SERVER_ERROR = 500         // Internal server error
```

## Essential Practices

### 1. No Database Operations

```php
// ✅ CORRECT - Use repository
public function getUser(string $id)
{
    $user = $this->userRepository->find($id);
    
    return $this->setCode(ResponseCode::SUCCESS->value)
        ->setMessage('User retrieved')
        ->setData(['user' => new UserResource($user)]);
}

// ❌ WRONG - Direct query
public function getUser(string $id)
{
    $user = User::find($id);
    // ...
}
```

### 2. Use Resources for Transformation

```php
// ✅ CORRECT
return $this->setCode(ResponseCode::SUCCESS->value)
    ->setData(['user' => new UserResource($user)]);

// ❌ WRONG
return $this->setCode(ResponseCode::SUCCESS->value)
    ->setData(['user' => $user->toArray()]);
```

### 3. Always Set Data as Array

```php
// ✅ CORRECT
return $this->setData([
    'user' => new UserResource($user),
    'token' => $token,
]);

// ❌ WRONG
return $this->setData(new UserResource($user));
```

### 4. Validate Permissions First

```php
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

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
            ->setMessage('Access denied. Missing required permission to perform this action.');
    } catch (\Exception $e) {
        return $this->setCode(ResponseCode::SERVER_ERROR->value)
            ->setMessage('An error occurred while retrieving products')
            ->setError($e->getMessage());
    }
}
```

**Key Points:**
- Validate permissions at the start of the method
- Catch `AccessDeniedHttpException` separately for permission errors
- Catch general `\Exception` for other errors
- Permission helper throws exception; service catches and converts to response code
- Keep try-catch focused: permission validation → data retrieval → response

### 5. Use Database Transactions

```php
public function createWithDetails(array $data)
{
    try {
        DB::beginTransaction();
        
        // Multiple operations
        $model = $this->repository->create($data);
        $model->relationships()->sync($data['related_ids']);
        
        DB::commit();
        
        return $this->setCode(ResponseCode::CREATED->value)
            ->setMessage('Created successfully')
            ->setData(['model' => new ModelResource($model)]);
    } catch (\Exception $e) {
        DB::rollBack();
        
        return $this->setCode(ResponseCode::SERVER_ERROR->value)
            ->setMessage('An error occurred')
            ->setError($e->getMessage());
    }
}
```

### 6. Handle Nullable Returns from Repository

```php
public function updateOrderStatus(string $orderId, array $request)
{
    try {
        // Repository method returns null if not found
        $updatedOrder = $this->orderRepository->updateStatus(
            $orderId, 
            $request->status
        );

        // Check for null and return appropriate response
        if (!$updatedOrder) {
            return $this->setCode(ResponseCode::NOT_FOUND->value)
                ->setMessage('Order not found.');
        }

        return $this->setCode(ResponseCode::SUCCESS->value)
            ->setMessage('Order status updated successfully.')
            ->setData(['order' => new OrderResource($updatedOrder)]);
    } catch (\Exception $e) {
        return $this->setCode(ResponseCode::SERVER_ERROR->value)
            ->setMessage('An error occurred while updating order status')
            ->setError($e->getMessage());
    }
}
```

**Key Pattern:**
- Repository methods return `?Model` (nullable type)
- Repository handles finding and returning null (not throwing exception)
- Service simply checks for null and returns appropriate response
- Reduces nesting and simplifies control flow
- Repository loads relationships; service doesn't need to call refresh()

## Common Patterns

### Create with Validation

```php
public function registerUser($request)
{
    try {
        $user = $this->repository->create($request->validated());
        $user->setRole(RoleEnum::USER->name);
        
        return $this->setCode(ResponseCode::CREATED->value)
            ->setMessage('User registered successfully')
            ->setData(['user' => new UserResource($user)]);
    } catch (\Exception $e) {
        return $this->setCode(ResponseCode::SERVER_ERROR->value)
            ->setMessage('Registration failed')
            ->setError($e->getMessage());
    }
}
```

### Update with Relationships

```php
public function updateUserRoles(string $userId, array $roleIds)
{
    try {
        $user = $this->repository->findWithRelationships($userId);
        
        if (!$user) {
            return $this->setCode(ResponseCode::NOT_FOUND->value)
                ->setMessage('User not found.');
        }

        $user->roles()->sync($roleIds);
        
        return $this->setCode(ResponseCode::SUCCESS->value)
            ->setMessage('Roles updated successfully.')
            ->setData(['user' => new UserResource($user->load('roles'))]);
    } catch (\Exception $e) {
        return $this->setCode(ResponseCode::SERVER_ERROR->value)
            ->setMessage('An error occurred while updating roles')
            ->setError($e->getMessage());
    }
}
```

**Pattern:**
- Repository returns null if not found
- Service checks null and returns NOT_FOUND response
- No need for `findOrFail()` exception handling

### Delete with Logging

```php
public function deleteProduct(string $id)
{
    try {
        $product = $this->repository->find($id);
        
        $this->repository->delete($id);
        
        return $this->setCode(ResponseCode::SUCCESS->value)
            ->setMessage('Product deleted successfully');
    } catch (\Exception $e) {
        return $this->setCode(ResponseCode::SERVER_ERROR->value)
            ->setError($e->getMessage());
    }
}
```

## Documentation Requirements

All methods in both interface and implementation must have comprehensive PHPDoc blocks:

**Interface (complete documentation):**
```php
/**
 * Authenticate a user with email and password.
 *
 * Validates user credentials, checks account status, revokes existing tokens,
 * and generates a new authentication token for API access.
 *
 * @param  array  $credentials  The login credentials with email and password
 * @param  string  $deviceName  The device name for token identification
 * @return ServiceApi Returns service response with user data and access token
 */
public function login(array $credentials, string $deviceName): ServiceApi;
```

**Implementation (duplicate full documentation for IDE support):**
```php
/**
 * Authenticate a user with email and password.
 *
 * Validates user credentials, checks account status, revokes existing tokens,
 * and generates a new authentication token for API access.
 *
 * @param  array  $credentials  The login credentials with email and password
 * @param  string  $deviceName  The device name for token identification
 * @return ServiceApi Returns service response with user data and access token
 */
public function login(array $credentials, string $deviceName): ServiceApi
{
    // Implementation
}
```

## Code Quality Rules

1. **No inline comments** - Use docblocks
2. **No database queries** - All through repositories
3. **Consistent response chain** - `setCode()->setMessage()->setData()`
4. **Always use ResponseCode enum** - Never hardcode status codes
5. **Try-catch all operations** - Return error responses
6. **Type hint all parameters** - Even if using $request
7. **Document all methods** - PHPDoc blocks required
8. **Organize by feature** - Use subdirectories
