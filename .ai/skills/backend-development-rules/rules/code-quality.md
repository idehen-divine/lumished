# Code Quality Rules

## Comment Philosophy

### ❌ NO Inline Comments

```php
// WRONG - Inline comments
public function login($request)
{
    // Check if user exists
    $user = $this->userRepository->findByEmail($request->email);
    
    // Verify password
    if (!$user || !Hash::check($request->password, $user->password)) {
        // Return unauthorized
        return $this->setCode(ResponseCode::UNAUTHORIZED->value)
            ->setMessage('Invalid credentials');
    }
    
    // Create token
    $token = $user->createToken('auth')->plainTextToken;
    
    // Return response
    return $this->setCode(ResponseCode::SUCCESS->value)
        ->setData(['token' => $token]);
}
```

### ✅ YES PHPDoc Blocks

```php
// CORRECT - PHPDoc blocks
/**
 * Authenticate a user with email and password.
 *
 * Validates user credentials and generates an authentication token.
 * Revokes any existing tokens before creating a new one.
 *
 * @param  mixed  $request  The login request with email and password
 * @return AuthServiceImplement Returns service response with authentication token
 */
public function login($request): AuthServiceImplement
{
    $user = $this->userRepository->findByEmail($request->email);

    if (!$user || !Hash::check($request->password, $user->password)) {
        return $this->setCode(ResponseCode::UNAUTHORIZED->value)
            ->setMessage('Invalid credentials');
    }

    $user->tokens()->delete();
    $token = $user->createToken('auth')->plainTextToken;

    return $this->setCode(ResponseCode::SUCCESS->value)
        ->setData(['token' => $token]);
}
```

## PHPDoc Requirements

**Rule:** PHPDoc must be present on both **interface and implementation** for full IDE support. Duplicate documentation to ensure hover tooltips work reliably across all IDEs.

### For Service/Repository Interfaces

```php
/**
 * Short description of what the method does.
 *
 * Longer explanation if needed. Include important behaviors,
 * side effects, and any special considerations.
 * Multiple paragraphs okay.
 *
 * @param  Type  $parameter  Description of parameter. Include constraints/expectations
 * @param  string  $email  The user's email address
 * @return ReturnType Description of what is returned and when
 * @throws ExceptionType When and why this exception is thrown
 */
public function methodName(Type $parameter): ReturnType;
```

### For Implementations

**Duplicate the full documentation from the interface** — do not use `@inheritDoc`:

```php
/**
 * Short description of what the method does.
 *
 * Longer explanation if needed. Include important behaviors,
 * side effects, and any special considerations.
 * Multiple paragraphs okay.
 *
 * @param  Type  $parameter  Description of parameter. Include constraints/expectations
 * @param  string  $email  The user's email address
 * @return ReturnType Description of what is returned and when
 * @throws ExceptionType When and why this exception is thrown
 */
public function methodName(Type $parameter): ReturnType
{
    // Implementation
}
```

### For Repository Methods

```php
/**
 * Find a user by email address.
 *
 * Retrieves a user with the specified email address along with
 * eager-loaded relationships (roles, permissions) to prevent N+1 queries.
 * Returns null if no user is found with the given email.
 *
 * @param  string  $email  The user's email address to search for
 * @return User|null Returns the User model instance or null if not found
 */
public function findByEmail(string $email): ?User
{
    return $this->model->where('email', $email)->first();
}
```

### For Constructors

```php
/**
 * Initialize the service with repository dependencies.
 *
 * @param  UserRepository  $userRepository  The user repository for data access
 * @param  ProductRepository  $productRepository  The product repository for data access
 */
public function __construct(
    protected UserRepository $userRepository,
    protected ProductRepository $productRepository
) {}
```

### For Models

```php
/**
 * Get the user's primary agent.
 *
 * Retrieves the agent assigned to this user. A user can have
 * at most one agent at a time.
 *
 * @return HasOne The agent relationship
 */
public function agent(): HasOne
{
    return $this->hasOne(Agent::class);
}
```

## Type Hints & Type Safety

### Implementations Must Have Type Hints

```php
// ✅ CORRECT - Type hints on implementation
class UserRepositoryImplement extends Eloquent implements UserRepository
{
    public function findByEmail(string $email): ?User
    {
        return $this->model->where('email', $email)->first();
    }
    
    public function create(array $data): User
    {
        return $this->model->create($data);
    }
}
```

### Interfaces Have Type Hints AND PHPDoc — Implementations Use @inheritDoc

Interfaces are the **single source of truth** for documentation and type contracts. Implementations inherit via `@inheritDoc`.

```php
// ✅ CORRECT - Interface has full PHPDoc + type hints
interface UserRepository extends Repository
{
    /**
     * Find a user by email address.
     *
     * @param  string  $email  The user's email address
     * @return User|null Returns the User model or null if not found
     */
    public function findByEmail(string $email): ?User;

    /**
     * Create a new user record.
     *
     * @param  array  $data  The user data
     * @return User The newly created user
     */
    public function create(array $data): User;
}

// ✅ CORRECT - Implementation uses @inheritDoc
class UserRepositoryImplement extends Eloquent implements UserRepository
{
    /** @inheritDoc */
    public function findByEmail(string $email): ?User
    {
        return $this->model->where('email', $email)->first();
    }

    /** @inheritDoc */
    public function create(array $data): User
    {
        return $this->model->create($data);
    }
}

// ❌ WRONG - Bare interface with no docs or types
interface UserRepository extends Repository
{
    public function findByEmail($email);

    public function create($data);
}

// ❌ WRONG - Duplicating PHPDoc in implementation instead of @inheritDoc
class UserRepositoryImplement extends Eloquent implements UserRepository
{
    /**
     * Find a user by email address.  ← don't repeat this
     */
    public function findByEmail(string $email): ?User
    {
        return $this->model->where('email', $email)->first();
    }
}
```

### Return Types Required

```php
// ✅ CORRECT
public function find(string $id): ?User
{
    // ...
}

public function getAll(): Collection
{
    // ...
}

// ❌ WRONG
public function find($id)
{
    // ...
}
```

## Naming Conventions

### Variables & Properties

```php
// ✅ CORRECT - Descriptive
$user = User::find($id);
$userRepository = app(UserRepository::class);
$isActive = true;
$emailVerified = false;

// ❌ WRONG - Vague/abbreviated
$u = User::find($id);
$repo = app(UserRepository::class);
$active = true;
$verified = false;
```

### Methods

```php
// ✅ CORRECT - Descriptive and specific
public function createUserWithRole($userData)
public function getAllActiveUsers()
public function findUserByEmail($email)
public function isLowStock()
public function hasPermission($permission)

// ❌ WRONG - Vague
public function create($data)
public function getAll()
public function find($email)
public function check()
public function can()
```

### Boolean Methods

```php
// ✅ CORRECT - is/has prefix for boolean methods
public function isActive(): bool
public function hasPermission(): bool
public function isExpired(): bool
public function canDelete(): bool
public function shouldSync(): bool

// ❌ WRONG - Missing prefix
public function active(): bool
public function permission(): bool
public function expired(): bool
```

## Method Length

```php
// ✅ CORRECT - Short and focused
public function login($request): LoginResponseService
{
    $user = $this->userRepository->findByEmail($request->email);

    if (!$user || !Hash::check($request->password, $user->password)) {
        return $this->setCode(ResponseCode::UNAUTHORIZED->value)
            ->setMessage('Invalid credentials');
    }

    return $this->setCode(ResponseCode::SUCCESS->value)
        ->setData(['user' => new UserResource($user)]);
}

// ❌ WRONG - Too long, multiple responsibilities
public function login($request): LoginResponseService
{
    // Email validation
    if (!filter_var($request->email, FILTER_VALIDATE_EMAIL)) {
        return $this->setCode(ResponseCode::BAD_REQUEST->value);
    }

    // User lookup
    $user = User::where('email', $request->email)->first();

    // Password check
    if (!$user || !Hash::check($request->password, $user->password)) {
        return $this->setCode(ResponseCode::UNAUTHORIZED->value);
    }

    // Account status check
    if ($user->status !== 'ACTIVE') {
        return $this->setCode(ResponseCode::FORBIDDEN->value);
    }

    // Token generation
    $user->tokens()->delete();
    $token = $user->createToken('auth')->plainTextToken;

    // Role assignment
    $user->assignRole('default');

    // Logging
    Log::info('User logged in', ['user_id' => $user->id]);

    // ... more code

    return $this->setCode(ResponseCode::SUCCESS->value)
        ->setData(['user' => new UserResource($user)]);
}
```

## Early Returns

```php
// ✅ CORRECT - Early returns reduce nesting
public function updateUser(string $id, array $data)
{
    $user = $this->userRepository->find($id);

    if (!$user) {
        return $this->setCode(ResponseCode::NOT_FOUND->value)
            ->setMessage('User not found');
    }

    if (!auth()->user()->can('update', $user)) {
        return $this->setCode(ResponseCode::FORBIDDEN->value);
    }

    // Main logic here...
    $this->userRepository->update($id, $data);

    return $this->setCode(ResponseCode::SUCCESS->value);
}

// ❌ WRONG - Deep nesting
public function updateUser(string $id, array $data)
{
    $user = $this->userRepository->find($id);

    if ($user) {
        if (auth()->user()->can('update', $user)) {
            // Main logic nested deep
            $this->userRepository->update($id, $data);

            return $this->setCode(ResponseCode::SUCCESS->value);
        } else {
            return $this->setCode(ResponseCode::FORBIDDEN->value);
        }
    } else {
        return $this->setCode(ResponseCode::NOT_FOUND->value);
    }
}
```

## Import Organization

```php
<?php

namespace App\Services\Auth;

// App namespace imports first (alphabetical)
use App\Enums\ResponseCode;
use App\Http\Resources\UserResource;
use App\Repositories\User\UserRepository;

// Framework imports
use Illuminate\Support\Facades\Hash;

// Third-party imports
use LaravelEasyRepository\ServiceApi;
```

## Error Handling

### In Services

```php
// ✅ CORRECT
try {
    $user = $this->userRepository->find($id);

    if (!$user) {
        return $this->setCode(ResponseCode::NOT_FOUND->value)
            ->setMessage('User not found');
    }

    $this->userRepository->update($id, $data);

    return $this->setCode(ResponseCode::SUCCESS->value)
        ->setData(['user' => new UserResource($user)]);

} catch (Exception $e) {
    return $this->setCode(ResponseCode::SERVER_ERROR->value)
        ->setMessage('An error occurred')
        ->setError($e->getMessage());
}
```

### In Repositories

```php
// ✅ CORRECT - Let exceptions bubble up to service
public function findById(string $id): ?User
{
    return $this->model->find($id);
}

// Service catches and handles
```

## Database Transactions

```php
// ✅ CORRECT
DB::beginTransaction();
try {
    // Multiple operations
    $product = $this->productRepository->create($data);
    $product->categories()->sync($data['category_ids']);

    DB::commit();

    return $this->setCode(ResponseCode::CREATED->value)
        ->setData(['product' => new ProductResource($product)]);

} catch (Exception $e) {
    DB::rollBack();

    return $this->setCode(ResponseCode::SERVER_ERROR->value)
        ->setError($e->getMessage());
}
```

## Formatting & Style

### PSR-12 Compliance

```php
// ✅ CORRECT
public function getUsersWithRoles(): Collection
{
    return $this->model
        ->with('roles')
        ->where('status', 'ACTIVE')
        ->get();
}

// Always use curly braces
if ($condition) {
    // ...
}

// Spaces around operators
$result = $a + $b;
$value = $condition ? 'yes' : 'no';
```

## Code Quality Checklist

When writing code, ensure:

- [ ] **No inline comments** - Use PHPDoc blocks
- [ ] **Type hints on interfaces AND implementations** - Required on both; implementations use `@inheritDoc`
- [ ] **Return types declared** - All methods
- [ ] **Descriptive names** - `$user`, `$roleId`, not `$u`, `$rid`
- [ ] **Methods focused** - One responsibility
- [ ] **Early returns** - Reduce nesting
- [ ] **Short methods** - Under 15 lines when possible
- [ ] **PHPDoc blocks** - All methods and constructors
- [ ] **Consistent naming** - Follow existing patterns
- [ ] **Error handling** - Try-catch where needed
- [ ] **No database in services** - Use repositories
- [ ] **No business logic in repositories** - Data access only
- [ ] **Type safety** - Use appropriate types
- [ ] **Imports organized** - Alphabetical within groups

## Tools

### Laravel Pint

Format code according to PSR-12:

```bash
# Fix code style
vendor/bin/pint --dirty

# Check without fixing
vendor/bin/pint --test
```

### PHPStan

Static analysis for type safety:

```bash
# Run analysis
vendor/bin/phpstan analyse app
```

### IDE Support

- Enable PHPStan in IDE
- Use PHP Intelephense for code completion
- Set up code formatting on save
