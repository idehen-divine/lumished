# Repository Implementation Rules

## Interface Definition

**Rule:** Repository interfaces define contracts with type hints and PHPDoc. Implementations duplicate full documentation for reliable IDE support.

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
     * Retrieves a user with the specified email address along with
     * eager-loaded relationships to prevent N+1 queries.
     *
     * @param  string  $email  The user's email address
     * @return User|null Returns the User model instance or null if not found
     */
    public function findByEmail(string $email): ?User;
}
```

## Core Implementation

```php
<?php

namespace App\Repositories\User;

use App\Models\User;
use L0n3ly\LaravelRepositoryWithService\Implementations\Eloquent;

class UserRepositoryImplement extends Eloquent implements UserRepository
{
    /**
     * Initialize the repository with User model.
     *
     * @param  User  $model  The User model instance
     */
    public function __construct(User $model)
    {
        $this->model = $model;
    }

    /**
     * Find user by email address.
     *
     * Retrieves a user with the specified email address along with
     * eager-loaded relationships to prevent N+1 queries.
     *
     * @param  string  $email  The user's email address
     * @return User|null Returns the User model instance or null if not found
     */
    public function findByEmail(string $email): ?User
    {
        return $this->model->where('email', $email)->first();
    }
}
```

## Extension Rules

1. **Extend `Eloquent`** from L0n3ly\LaravelRepositoryWithService\Implementations\Eloquent
2. **Implement interface** - Must match interface methods
3. **Set $this->model** in constructor

## Core Methods (from L0n3ly\LaravelRepositoryWithService\Implementations\Eloquent)

```php
all()                           // All records
find($id)                       // Find by ID
findOrFail($id)                 // Find or throw
create($data)                   // Create record
update($id, $data)              // Update record
delete($id)                     // Delete single
destroy(array $ids)             // Delete multiple
query()                         // Fresh query builder
updateOrCreate($where, $values) // Update or create
firstOrCreate($where, $values)  // Find or create
```

## Methods Returning Nullable Types

For methods that may not find a record, return `?Model` instead of throwing exceptions. Let the service handle null checks:

```php
/**
 * Update order status and load relationships.
 *
 * Updates the order status to the provided value and reloads
 * related data (user, items, transactions). Returns null if order not found.
 *
 * @param  string  $id  The order ID
 * @param  string  $status  The new status value
 * @return \App\Models\Order|null The updated order with loaded relationships, or null if not found
 */
public function updateStatus(string $id, string $status): ?\App\Models\Order
{
    $order = $this->model->find($id);

    if (!$order) {
        return null;
    }

    $order->status = $status;
    $order->save();

    return $order->load(['user', 'items.product', 'transactions']);
}
```

**Key Pattern:**
- Return nullable type: `?Model`
- Find the record, return `null` if not found
- Don't use `findOrFail()` that throws exceptions
- Load relationships with `load()` before returning
- Service checks for null and returns appropriate response
- Keeps repository focused on data access
- Simplifies service logic by reducing try-catch nesting

**Usage in Service:**
```php
$updatedOrder = $this->orderRepository->updateStatus($orderId, $request->status);

if (!$updatedOrder) {
    return $this->setCode(ResponseCode::NOT_FOUND->value)
        ->setMessage('Order not found.');
}

return $this->setCode(ResponseCode::SUCCESS->value)
    ->setData(['order' => new OrderResource($updatedOrder)]);
```

## Essential Practices

### 1. Eager Load Relationships

```php
// ✅ CORRECT - Prevent N+1
public function getAllUsers(): Collection
{
    return $this->model
        ->with(['roles', 'permissions'])
        ->get();
}

// ❌ WRONG - Causes N+1
public function getAllUsers(): Collection
{
    return $this->model->get();
}
```

### 2. Select Only Needed Columns

```php
// ✅ CORRECT
return $this->model
    ->select('id', 'email', 'first_name')
    ->with('roles')
    ->first();

// ❌ WRONG
return $this->model->first(); // Selects all columns
```

### 3. Return Models, Not Arrays

```php
// ✅ CORRECT
public function findByEmail(string $email): ?User
{
    return $this->model->where('email', $email)->first();
}

// ❌ WRONG
public function findByEmail(string $email): ?array
{
    return $this->model->where('email', $email)->first()->toArray();
}
```

### 4. Use Query Builder Methods

```php
where()         // Basic WHERE clause
whereBelongsTo()// For belongsTo relationships
with()          // Eager load
select()        // Choose columns
first()         // Get one
get()           // Get many
paginate()      // Paginated results
pluck()         // Extract single column
count()         // Count results
exists()        // Check existence
distinct()      // Remove duplicates
orderBy()       // Sort results
groupBy()       // Group results
```

### 5. Use Helper for Pagination

```php
public function getAllProducts(): \Illuminate\Contracts\Pagination\LengthAwarePaginator
{
    $query = $this->model->with('categories');

    return helpers()->queryableHelper()->fetchWithFilters($query);
}
```

This helper handles:
- Pagination
- Filtering by status
- Searching
- Sorting
- Per-page limits

### 6. Hash Passwords in Repository

```php
public function create(array $data): User
{
    $data['password'] = Hash::make($data['password']);

    return $this->model->create($data);
}
```

### 7. Remove Unnecessary Data

```php
public function create(array $data): User
{
    // Filter out password confirmation and other fields
    $filteredData = $data->except(['password_confirmation', 'token']);

    return $this->model->create($filteredData->toArray());
}
```

## Common Patterns

### Finding with Relationships

```php
public function findWithDetails(string $id): ?User
{
    return $this->model
        ->where('id', $id)
        ->with(['roles', 'permissions', 'agent'])
        ->first();
}

/**
 * Find user by ID with all relationships loaded.
 *
 * Returns null if user not found, allowing service to handle
 * the not-found response appropriately.
 */
public function findWithRelationships(string $id): ?User
{
    return $this->model
        ->with(['roles', 'permissions', 'agent'])
        ->find($id);
}
```

### Creating with Relationships

```php
public function createWithRole(array $data): User
{
    $user = $this->model->create($data);

    $user->setRole(RoleEnum::USER->name);

    return $user->load(['roles']);
}
```

### Filtering and Pagination

```php
public function getAllActive(): LengthAwarePaginator
{
    $query = $this->model
        ->where('status', 'ACTIVE')
        ->with('roles');

    return helpers()->queryableHelper()->fetchWithFilters($query);
}
```

### Syncing Relationships

```php
public function syncRoles(string $userId, array $roleIds): User
{
    $user = $this->model->find($userId);

    $user->roles()->sync($roleIds);

    return $user->load('roles');
}
```

## Documentation Requirements

All methods must have comprehensive PHPDoc blocks:

```php
/**
 * Find active users with specific role.
 *
 * Retrieves all users with 'ACTIVE' status and the specified role,
 * with eager-loaded relationships to prevent N+1 queries.
 *
 * @param  string  $roleName  The role name to filter by
 * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator Paginated list of users
 */
public function findActiveByRole(string $roleName): LengthAwarePaginator
{
    $query = $this->model
        ->where('status', 'ACTIVE')
        ->whereHas('roles', fn($q) => $q->where('name', $roleName))
        ->with('roles');

    return helpers()->queryableHelper()->fetchWithFilters($query);
}
```

## Code Quality Rules

1. **No inline comments** - Use docblocks
2. **Eager load relationships** - Always use `with()` or `load()`
3. **Type hints for return types** - Required, use nullable `?Model` for "not found" scenarios
4. **Return null for not found** - Don't use `findOrFail()`, let service handle null checks
5. **Load relationships before returning** - Use `load()` to refresh after modifications
6. **One concern per method** - Single responsibility
7. **Organize by feature** - Use subdirectories
8. **Test data access** - Write repository tests
