# Enum Implementation Rules

Enums provide type-safe constants for application values like permissions, roles, statuses, and other fixed options.

⚠️ **CRITICAL: Enums must be NON-BACKED by default. Use UPPERCASE case names. Never modify without understanding all implications.**

## Core Purpose

- Define application constants as type-safe enums
- Prevent invalid values from being used in code
- Enable strict type checking via type hints
- Use enum `->name` property (the case name, not a string value)
- Reduce bugs from typos in magic strings
- Document valid options for permissions, roles, statuses, etc.

## Location & Setup

Enums live in `app/Enums/` organized by domain or feature:

```
app/Enums/
├── PermissionEnum.php          # All application permissions
├── RoleEnum.php                # All application roles
├── OrderStatusEnum.php         # Order-related statuses
├── PaymentStatusEnum.php       # Payment-related statuses
├── UserStatusEnum.php          # User-related statuses
└── AdminPanelPermissionEnum.php # Admin-specific permissions (when applicable)
```

## Enum Types

### Non-Backed Enums (DEFAULT)

Non-backed enums have case names only. Use the `->name` property to get the case name as a string.

**✅ CORRECT - Non-backed enum (DEFAULT):**

```php
<?php

namespace App\Enums;

enum PermissionEnum
{
    case VIEW_PRODUCTS;
    case CREATE_PRODUCTS;
    case UPDATE_PRODUCTS;
    case DELETE_PRODUCTS;
    case VIEW_USERS;
    case CREATE_USERS;
    case UPDATE_USERS;
    case DELETE_USERS;
}
```

Usage:
```php
$permission = PermissionEnum::VIEW_PRODUCTS;
$name = $permission->name;  // "VIEW_PRODUCTS" (string)

// Type-safe validation
helpers()->permissionHelper()->validatePermission(PermissionEnum::VIEW_PRODUCTS);
```

### Backed Enums (RARE - Only When User Specifies)

Backed enums have case names AND backing values. Use only when explicitly requested.

**❌ RARE - Only use if user specifically asks:**

```php
<?php

namespace App\Enums;

enum OrderStatusEnum: string
{
    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
}
```

Usage:
```php
$status = OrderStatusEnum::PENDING;
$name = $status->name;    // "PENDING" (enum case name)
$value = $status->value;  // "pending" (backing value)
```

**When to Use Backed Enums:**
- Database enum columns that need specific values
- Storing external API status codes
- Compatibility with existing string-based systems
- Only if user explicitly requests it

## Naming Conventions

### ✅ CORRECT

- **Class names** - PascalCase: `OrderStatusEnum`, `PermissionEnum`, `RoleEnum`
- **Case names** - UPPER_SNAKE_CASE: `VIEW_PRODUCTS`, `CREATE_USERS`, `DELETE_ORDERS`
- **Directory** - `app/Enums/`

```php
namespace App\Enums;

enum AdminPanelPermissionEnum
{
    case VIEW_PRODUCTS;      // ✅ UPPER_SNAKE_CASE
    case CREATE_PRODUCTS;
    case UPDATE_PRODUCTS;
    case DELETE_PRODUCTS;
}
```

### ❌ WRONG

```php
// ❌ WRONG - Mixed case in enum names
enum AdminPanelPermissionEnum
{
    case viewProducts;       // ❌ camelCase
    case CreateProducts;     // ❌ PascalCase
    case delete_products;    // ❌ lowercase
}

// ❌ WRONG - Class name not PascalCase
enum admin_permission
{
    case VIEW_PRODUCTS;
}

// ❌ WRONG - Class name without "Enum" suffix
enum Permission
{
    case VIEW_PRODUCTS;
}
```

## Common Enum Patterns

### Permission Enums

Define all application permissions. Use with `PermissionHelper`:

```php
<?php

namespace App\Enums;

enum PermissionEnum
{
    case VIEW_PRODUCTS;
    case CREATE_PRODUCTS;
    case UPDATE_PRODUCTS;
    case DELETE_PRODUCTS;

    case VIEW_ORDERS;
    case CREATE_ORDERS;
    case UPDATE_ORDERS;
    case CANCEL_ORDERS;

    case VIEW_USERS;
    case CREATE_USERS;
    case UPDATE_USERS;
    case DELETE_USERS;
}
```

**Usage in Service:**

```php
use App\Enums\PermissionEnum;

public function getAllProducts()
{
    try {
        helpers()->permissionHelper()->validatePermission(
            PermissionEnum::VIEW_PRODUCTS
        );

        // ... rest of logic

        return $this->setCode(ResponseCode::SUCCESS->value)
            ->setData(['products' => ProductResource::collection($products)]);
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

### Role Enums

Define application roles for authorization:

```php
<?php

namespace App\Enums;

enum RoleEnum
{
    case ADMIN;
    case MANAGER;
    case USER;
    case GUEST;
}
```

**Usage in Service:**

```php
use App\Enums\RoleEnum;

public function assignRole(string $userId, RoleEnum $role)
{
    $user = $this->userRepository->find($userId);

    if (!$user) {
        return $this->setCode(ResponseCode::NOT_FOUND->value)
            ->setMessage('User not found.');
    }

    $user->assignRole($role->name);

    return $this->setCode(ResponseCode::SUCCESS->value)
        ->setData(['user' => new UserResource($user->load('roles'))]);
}
```

### Status Enums

Define statuses for domain models (orders, payments, etc.):

```php
<?php

namespace App\Enums;

enum OrderStatusEnum
{
    case PENDING;
    case PROCESSING;
    case SHIPPED;
    case DELIVERED;
    case CANCELLED;
    case REFUNDED;
}
```

**Usage in Repository:**

```php
use App\Enums\OrderStatusEnum;

public function findByStatus(OrderStatusEnum $status): LengthAwarePaginator
{
    $query = $this->model
        ->where('status', $status->name)
        ->with('user', 'items');

    return helpers()->queryableHelper()->fetchWithFilters($query);
}
```

**Usage in Model:**

```php
use App\Enums\OrderStatusEnum;

class Order extends Model
{
    protected $casts = [
        'status' => OrderStatusEnum::class,
    ];

    public function isPending(): bool
    {
        return $this->status === OrderStatusEnum::PENDING;
    }

    public function isDelivered(): bool
    {
        return $this->status === OrderStatusEnum::DELIVERED;
    }
}
```

### Feature Flags / Boolean Enums

Use for binary choices with semantic names:

```php
<?php

namespace App\Enums;

enum FeatureStateEnum
{
    case ENABLED;
    case DISABLED;
}

enum ActiveStatusEnum
{
    case ACTIVE;
    case INACTIVE;
}
```

## Integration with Helpers

### With PermissionHelper

PermissionHelper requires an enum type and uses `->name` property:

```php
// Create permissions in seeder
Permission::create(['name' => PermissionEnum::VIEW_PRODUCTS->name]);
Permission::create(['name' => PermissionEnum::CREATE_PRODUCTS->name]);

// Validate in service
helpers()->permissionHelper()->validatePermission(PermissionEnum::VIEW_PRODUCTS);
```

### With Models

Cast enum fields to Eloquent's native enum casting:

```php
use App\Enums\OrderStatusEnum;

class Order extends Model
{
    protected $casts = [
        'status' => OrderStatusEnum::class,
    ];
}

// Automatic casting
$order = Order::find(1);
$order->status;  // Returns OrderStatusEnum::PENDING instance, not string
$order->status->name;  // "PENDING"
```

### With Repositories

Use enum type hints for type safety:

```php
use App\Enums\OrderStatusEnum;

public function findByStatus(OrderStatusEnum $status): Collection
{
    return $this->model
        ->where('status', $status->name)
        ->get();
}

// Usage
$orders = $repository->findByStatus(OrderStatusEnum::PENDING);
```

## Creating Enums from Artisan

Laravel doesn't have a built-in `make:enum` command, so create them manually:

```bash
# Create file manually
touch app/Enums/YourEnum.php
```

Then structure it exactly as shown in the patterns above.

## Code Quality Rules

1. **Non-backed by default** - Only use backed enums if user explicitly requests it
2. **UPPER_SNAKE_CASE for cases** - `VIEW_PRODUCTS`, not `viewProducts` or `View_Products`
3. **PascalCase for enum class** - `OrderStatusEnum`, not `order_status_enum`
4. **Use ->name for string value** - Non-backed enums use `$enum->name`, not `$enum->value`
5. **Type hint all enum parameters** - `function setStatus(OrderStatusEnum $status): void`
6. **Never pass strings** - Pass enum cases, not string values: `setStatus(OrderStatusEnum::PENDING)` not `setStatus('pending')`
7. **One concern per enum** - Don't mix permission, role, and status enums
8. **Document enum purpose** - Add PHPDoc explaining when each case is used
9. **Use in services/repositories** - Pass enums through type hints, not strings
10. **Never duplicate enum cases** - Check existing enums before creating new ones

## Example: Complete Enum Usage Flow

**Define the Enum:**

```php
<?php

namespace App\Enums;

enum OrderStatusEnum
{
    case PENDING;
    case PROCESSING;
    case SHIPPED;
    case DELIVERED;
}
```

**Create in Database Seeder:**

```php
// Create order status (if storing in database)
$order = Order::create([
    'status' => OrderStatusEnum::PENDING->name,
]);
```

**Cast in Model:**

```php
class Order extends Model
{
    protected $casts = [
        'status' => OrderStatusEnum::class,
    ];
}
```

**Use in Repository:**

```php
public function findByStatus(OrderStatusEnum $status): Collection
{
    return $this->model
        ->where('status', $status->name)
        ->get();
}
```

**Use in Service:**

```php
public function shipOrder(string $id)
{
    try {
        $order = $this->orderRepository->find($id);

        if (!$order) {
            return $this->setCode(ResponseCode::NOT_FOUND->value)
                ->setMessage('Order not found.');
        }

        $order->status = OrderStatusEnum::SHIPPED;
        $order->save();

        return $this->setCode(ResponseCode::SUCCESS->value)
            ->setData(['order' => new OrderResource($order)]);
    } catch (\Exception $e) {
        return $this->setCode(ResponseCode::SERVER_ERROR->value)
            ->setError($e->getMessage());
    }
}
```

**Type Safety Benefit:**

```php
// ✅ This works
$status = OrderStatusEnum::PENDING;

// ❌ This fails at compile-time (not runtime)
$status = OrderStatusEnum::INVALID;  // IDE/type checker catches this

// ❌ No type safety without enum
function setStatus(string $status) { }  // Could accept any string
setStatus('invalid-status');  // Bug discovered at runtime

// ✅ Type-safe with enum
function setStatus(OrderStatusEnum $status) { }
setStatus(OrderStatusEnum::INVALID);  // IDE error, caught immediately
```

## Non-Backed vs Backed Decision Tree

```
Is this a permission enum?
└─ YES → NON-BACKED (use ->name with PermissionHelper)
└─ NO

Is this a role enum?
└─ YES → NON-BACKED (use ->name for role assignments)
└─ NO

Is this a status enum for the database?
└─ YES → Check if you need specific values
    └─ User didn't specify a backing type → NON-BACKED (store ->name)
    └─ User explicitly requested backed with specific values → BACKED
└─ NO

Is this a feature flag / boolean?
└─ YES → NON-BACKED (ENABLED/DISABLED, ACTIVE/INACTIVE)
└─ NO → NON-BACKED (default)
```

**Rule of Thumb:** 99% of enums should be non-backed. Only use backed if the user explicitly says "this enum should have values like X, Y, Z."
