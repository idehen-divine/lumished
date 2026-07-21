# Model Implementation Rules

## Core Structure

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Traits\HasRoles;

class User extends Model
{
    use HasFactory, HasUuids, HasRoles;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'password',
        'status',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'date_of_birth' => 'date',
        'is_active' => 'boolean',
        'metadata' => 'json',
    ];

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    public function agent()
    {
        return $this->hasOne(Agent::class);
    }

    public function permissions()
    {
        return $this->belongsToMany(Permission::class);
    }
}
```

## Essential Rules

### 1. Use HasUuids Trait

```php
// ✅ CORRECT
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class User extends Model
{
    use HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;
}

// ❌ WRONG - Manual UUID generation
protected static function boot()
{
    parent::boot();
    static::creating(function ($model) {
        $model->id = (string) Str::uuid();
    });
}
```

### 2. Use first_name and last_name

```php
// ✅ CORRECT
protected $fillable = ['first_name', 'last_name', 'email'];

public function getFullNameAttribute(): string
{
    return "{$this->first_name} {$this->last_name}";
}

// ❌ WRONG - Single name field
protected $fillable = ['name', 'email'];
```

### 3. Define Relationships with Return Types

```php
// ✅ CORRECT - Type hints required
public function agent(): HasOne
{
    return $this->hasOne(Agent::class);
}

public function roles(): BelongsToMany
{
    return $this->belongsToMany(Role::class);
}

// ❌ WRONG - No type hints
public function agent()
{
    return $this->hasOne(Agent::class);
}
```

### 4. Use Proper Relationship Types

```php
// One-to-One
public function agent(): HasOne
{
    return $this->hasOne(Agent::class);
}

// One-to-Many
public function properties(): HasMany
{
    return $this->hasMany(Property::class);
}

// Belongs-To
public function role(): BelongsTo
{
    return $this->belongsTo(Role::class);
}

// Many-to-Many
public function roles(): BelongsToMany
{
    return $this->belongsToMany(Role::class);
}

// Many-to-Many with pivot data
public function roles(): BelongsToMany
{
    return $this->belongsToMany(Role::class)
        ->withTimestamps()
        ->withPivot('assigned_at');
}
```

### 5. Use Casts for Type Conversion

```php
protected $casts = [
    'email_verified_at' => 'datetime',
    'password' => 'hashed',
    'date_of_birth' => 'date',
    'is_active' => 'boolean',
    'metadata' => 'json',
    'type' => ProductTypeEnum::class,
    'prices' => 'array',
];
```

### 6. Add Accessors for Computed Fields

```php
// ✅ CORRECT
public function getFullNameAttribute(): string
{
    return trim("{$this->first_name} {$this->last_name}");
}

// Usage: $user->full_name (no parentheses)

// For complex logic, use functions
public function getAgeAttribute(): ?int
{
    return $this->date_of_birth?->diffInYears();
}
```

### 7. Use boot() for Auto-Generation

```php
protected static function boot(): void
{
    parent::boot();

    static::creating(function ($product) {
        $product->sku = static::generateUniqueSku($product->name);
    });
}

protected static function generateUniqueSku(string $name): string
{
    $nameParts = explode(' ', $name);
    $prefix = '';

    if (count($nameParts) >= 2) {
        $prefix = strtoupper(substr($nameParts[0], 0, 3) . substr($nameParts[1], 0, 2));
    } else {
        $prefix = strtoupper(substr($name, 0, 5));
    }

    $prefix = preg_replace('/[^A-Z0-9]/', '', $prefix);

    do {
        $random = strtoupper(Str::random(6));
        $sku = $prefix . '-' . $random;
    } while (static::where('sku', $sku)->exists());

    return $sku;
}
```

## Role & Permission Methods (If Using Spatie)

```php
use Spatie\Permission\Traits\HasRoles;

class User extends Model
{
    use HasRoles;

    /**
     * Set the user's role, replacing any existing roles.
     *
     * @param  string  $roleName  The name of the role to assign
     */
    public function setRole(string $roleName): void
    {
        if ($this->roles()->exists()) {
            $this->roles()->detach();
        }

        $this->assignRole($roleName);
    }

    /**
     * Get all permission names for this user.
     *
     * @return array List of permission names
     */
    public function getAllPermissionNames(): array
    {
        return $this->getAllPermissions()->pluck('name')->toArray();
    }

    /**
     * Check if user has a permission.
     *
     * @param  string|PermissionEnum  $permission  Permission name or enum
     */
    public function hasPermission(string|PermissionEnum $permission): bool
    {
        $permissionName = $permission instanceof PermissionEnum
            ? $permission->name
            : $permission;

        return $this->hasPermissionTo($permissionName);
    }
}
```

## Business Logic Helper Methods

```php
class Product extends Model
{
    /**
     * Check if the product is low in stock.
     */
    public function isLowStock(): bool
    {
        return $this->stock_quantity <= $this->minimum_stock;
    }

    /**
     * Check if the product is currently available for purchase.
     */
    public function isAvailable(): bool
    {
        return $this->status === 'ACTIVE' && $this->stock_quantity > 0;
    }

    /**
     * Get the discounted price.
     */
    public function getDiscountedPrice(): float
    {
        if (!$this->discount_percent) {
            return $this->price;
        }

        return $this->price * (1 - ($this->discount_percent / 100));
    }
}
```

## Common Patterns

### Relationships with Conditions

```php
public function activeRoles(): BelongsToMany
{
    return $this->belongsToMany(Role::class)
        ->where('is_active', true);
}

public function publicProperties(): HasMany
{
    return $this->hasMany(Property::class)
        ->where('is_public', true)
        ->orderBy('created_at', 'desc');
}
```

### Scopes

```php
public function scopeActive($query)
{
    return $query->where('status', 'ACTIVE');
}

public function scopeInactive($query)
{
    return $query->where('status', 'INACTIVE');
}

// Usage: User::active()->get()
```

### Soft Deletes (Optional)

```php
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use SoftDeletes;
}
```

## Documentation

All methods should have PHPDoc blocks:

```php
/**
 * Get the user's primary agent.
 *
 * Retrieves the agent associated with this user.
 * Returns null if no agent is assigned.
 *
 * @return Agent|null The associated agent or null
 */
public function agent(): HasOne
{
    return $this->hasOne(Agent::class);
}
```

## Code Quality Rules

1. **Use HasUuids trait** - For UUID primary keys
2. **Set $keyType = 'string'** - For UUID models
3. **Use first_name and last_name** - Never single name
4. **Type hint relationships** - Return types required
5. **Use casts** - For type conversion
6. **Add accessors** - For computed fields
7. **Use boot()** - For auto-generation
8. **No inline comments** - Use docblocks
9. **One responsibility** - Keep methods focused
10. **Document all methods** - PHPDoc blocks required
