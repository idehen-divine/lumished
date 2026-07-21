# Repository-Service Pattern

## Overview

This application uses the **L0n3ly Laravel Repository With Service** package with automatic container binding.

## Naming Convention

### Interfaces
- Repository: `App\Repositories\{Feature}\{Name}Repository`
- Service: `App\Services\{Feature}\{Name}Service`

### Implementations
- Repository: `App\Repositories\{Feature}\{Name}RepositoryImplement`
- Service: `App\Services\{Feature}\{Name}ServiceImplement`

## Auto-Binding

The `RepositoryAutoBindProvider` automatically binds:
```
App\Repositories\{Feature}\{Name}Repository
    ↓
App\Repositories\{Feature}\{Name}RepositoryImplement

App\Services\{Feature}\{Name}Service
    ↓
App\Services\{Feature}\{Name}ServiceImplement
```

**No manual binding required in service providers.**

## Repository Interface Example

```php
<?php

namespace App\Repositories\User;

use App\Models\User;
use L0n3ly\LaravelRepositoryWithService\Contracts\Repository;

interface UserRepository extends Repository
{
    /**
     * Find user by email address.
     *
     * @param  string  $email  The user's email address
     * @return User|null The user model or null if not found
     */
    public function findUserByEmail(string $email): ?User;
    
    /**
     * Create a new user record.
     *
     * @param  array  $data  The user data
     * @return User The newly created user
     */
    public function createUser(array $data): User;
    
    /**
     * Update user's last login timestamp.
     *
     * @param  string  $userId  The user ID
     * @return User|null The updated user or null if not found
     */
    public function updateLastLogin(string $userId): ?User;
}
```

### Rules
1. Extend `L0n3ly\LaravelRepositoryWithService\Contracts\Repository`
2. **MUST have type hints** - On parameters and return types
3. **MUST have PHPDoc** - Document all methods
4. All methods public

## Repository Implementation Example

```php
<?php

namespace App\Repositories\User;

use App\Models\User;
use L0n3ly\LaravelRepositoryWithService\Implementations\Eloquent;

class UserRepositoryImplement extends Eloquent implements UserRepository
{
    public function __construct(User $model)
    {
        $this->model = $model;
    }

    /**
     * Find user by email address.
     *
     * @param  string  $email  The user's email address
     * @return User|null The user model or null if not found
     */
    public function findUserByEmail(string $email): ?User
    {
        return $this->model->where('email', $email)->first();
    }
    
    /**
     * Create a new user record.
     *
     * @param  array  $data  The user data
     * @return User The newly created user
     */
    public function createUser(array $data): User
    {
        return $this->model->create($data);
    }
    
    /**
     * Update user's last login timestamp.
     *
     * @param  string  $userId  The user ID
     * @return User|null The updated user or null if not found
     */
    public function updateLastLogin(string $userId): ?User
    {
        $user = $this->model->find($userId);
        
        if ($user) {
            $user->update(['last_login_at' => now()]);
        }
        
        return $user;
    }
}
```

## Service Interface Example

```php
<?php

namespace App\Services\Auth;

use L0n3ly\LaravelRepositoryWithService\Contracts\BaseService;
use L0n3ly\LaravelRepositoryWithService\Services\ServiceApi;

interface AuthService extends BaseService
{
    /**
     * Register a new user account.
     *
     * @param  array  $data  The registration data
     * @return ServiceApi The service response
     */
    public function register(array $data): ServiceApi;
    
    /**
     * Authenticate a user with credentials.
     *
     * @param  array  $credentials  The login credentials
     * @param  string  $deviceName  The device name for token
     * @return ServiceApi The service response with token
     */
    public function login(array $credentials, string $deviceName): ServiceApi;
    
    /**
     * Logout the authenticated user.
     *
     * @param  mixed  $user  The authenticated user
     * @return ServiceApi The service response
     */
    public function logout($user): ServiceApi;
    
    /**
     * Get authenticated user profile.
     *
     * @param  mixed  $user  The authenticated user
     * @return ServiceApi The service response with user data
     */
    public function profile($user): ServiceApi;
}
```

### Rules
1. Extend `L0n3ly\LaravelRepositoryWithService\Contracts\BaseService`
2. **MUST have type hints** - On parameters and return types
3. **MUST have PHPDoc** - Document all methods
4. All methods public

## Service Implementation Example

```php
<?php

namespace App\Services\Auth;

use App\Enums\ResponseCode;
use App\Repositories\User\UserRepository;
use L0n3ly\LaravelRepositoryWithService\Services\ServiceApi;

class AuthServiceImplement extends ServiceApi implements AuthService
{
    public function __construct(protected UserRepository $userRepository) {}

    /**
     * Register a new user account.
     *
     * @param  array  $data  The registration data
     * @return ServiceApi The service response
     */
    public function register(array $data): ServiceApi
    {
        try {
            $user = $this->userRepository->createUser($data);
            
            return $this->setCode(ResponseCode::CREATED->value)
                ->setMessage('User registered successfully')
                ->setData(['user' => $user]);
        } catch (\Exception $e) {
            return $this->setCode(ResponseCode::SERVER_ERROR->value)
                ->setError($e->getMessage());
        }
    }

    /**
     * Authenticate a user with credentials.
     *
     * @param  array  $credentials  The login credentials
     * @param  string  $deviceName  The device name for token
     * @return ServiceApi The service response with token
     */
    public function login(array $credentials, string $deviceName): ServiceApi
    {
        // ... implementation
    }

    /**
     * Logout the authenticated user.
     *
     * @param  mixed  $user  The authenticated user
     * @return ServiceApi The service response
     */
    public function logout($user): ServiceApi
    {
        // ... implementation
    }

    /**
     * Get authenticated user profile.
     *
     * @param  mixed  $user  The authenticated user
     * @return ServiceApi The service response with user data
     */
    public function profile($user): ServiceApi
    {
        // ... implementation
    }
}

## Core Differences

| Layer | Responsibility | Returns | Uses |
|-------|---|---|---|
| **Repository** | Data access only | Models/Collections | Query builder, Eloquent |
| **Service** | Business logic | Service response | Repositories, Resources |
| **Controller** | HTTP handling | JsonResponse | Services, Form requests |

## Data Flow

```
HTTP Request
    ↓
Controller (validates via Form Request)
    ↓
Service (orchestrates business logic via Repository)
    ↓
Repository (accesses database)
    ↓
Model
    ↓
Database
    ↓
Model → Repository → Service → Resource → JsonResponse
```

## Key Principles

1. **Controllers are thin** - Delegate to services
2. **Services orchestrate** - Business logic & error handling
3. **Repositories isolate** - All database queries here
4. **Models are pure** - Relationships and casts only
5. **No database in services** - Everything through repositories
6. **No business logic in repositories** - Only data access

## Injection Pattern

```php
// Repository
public function __construct(protected UserRepository $userRepository) {}

// Service
public function __construct(
    protected UserRepository $userRepository,
    protected ProductRepository $productRepository
) {}

// Controller
public function __construct(protected AuthService $authService) {}
```

## Auto-Loading

All repositories and services are automatically available via constructor injection:

```php
class ProductController extends Controller
{
    // This works automatically - no manual binding needed
    public function __construct(protected ProductService $productService) {}
}
```

## Testing

Use the same injection pattern in tests:

```php
$userRepository = app(UserRepository::class);
$authService = app(AuthService::class);
```
