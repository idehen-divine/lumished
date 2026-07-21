# Migration Implementation Rules

## UUID Primary Keys

```php
Schema::create('users', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('email')->unique();
    $table->string('password');
    $table->timestamps();
});
```

## Foreign Keys with UUIDs

```php
$table->foreignUuid('user_id')
    ->constrained('users')
    ->onDelete('cascade');

$table->foreignUuid('agent_id')
    ->nullable()
    ->constrained('agents')
    ->onDelete('set null');
```

## Enum Columns

### Using All Enum Cases

```php
use App\Enums\ProductTypeEnum;

$table->enum('type', array_column(ProductTypeEnum::cases(), 'name'));

// Or if enum has a names() helper method
$table->enum('type', ProductTypeEnum::names());
```

### Using Subset of Enum Cases

Only manually list when NOT using all enum values:

```php
use App\Enums\UserStatusEnum;

$table->enum('status', [
    UserStatusEnum::ACTIVE->name,
    UserStatusEnum::INACTIVE->name,
    UserStatusEnum::PENDING->name,
]);
```

### Enum with Default Value

```php
$table->enum('status', UserStatusEnum::names())
    ->default(UserStatusEnum::PENDING->name);
```

## Common Column Types

### Text

```php
$table->string('email', 255);           // VARCHAR(255)
$table->text('description');            // TEXT
$table->longText('content');            // LONGTEXT
```

### Numeric

```php
$table->integer('quantity');            // INT - quantities
$table->unsignedBigInteger('price');    // BIGINT - money in cents
$table->decimal('percentage', 5, 2);    // DECIMAL - percentages
$table->float('rating');                // FLOAT - ratings
```

### Date/Time

```php
$table->timestamp('published_at')->nullable();
$table->date('birth_date');
$table->dateTime('scheduled_at');
```

### Boolean

```php
$table->boolean('is_active')->default(true);
$table->boolean('is_verified')->default(false);
```

### JSON

```php
$table->json('metadata')->nullable();
$table->json('tags')->default('[]');
$table->json('pricing_rules')->nullable();
```

### UUID

```php
$table->uuid('id')->primary();
$table->uuid('external_id')->unique()->nullable();
$table->foreignUuid('user_id');
```

## Indexes

```php
// Single column index
$table->index('email');
$table->index('status');

// Unique index
$table->unique('email');
$table->unique('sku');

// Composite index
$table->index(['user_id', 'status']);

// Unique composite index
$table->unique(['email', 'deleted_at']);

// Text search index
$table->fullText('description');
```

## Soft Deletes

```php
$table->softDeletes();  // Adds deleted_at column
```

## Timestamps

```php
$table->timestamps();   // Adds created_at and updated_at
```

## Nullable Fields

```php
$table->string('middle_name')->nullable();
$table->text('bio')->nullable();
$table->dateTime('last_login_at')->nullable();
```

## Default Values

```php
$table->boolean('is_verified')->default(false);
$table->integer('login_count')->default(0);
$table->enum('status', ['ACTIVE', 'INACTIVE'])->default('INACTIVE');
$table->timestamp('created_at')->useCurrent();
```

## Complete Example Migration

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            
            // Foreign keys
            $table->foreignUuid('user_id')->constrained()->onDelete('cascade');
            $table->foreignUuid('category_id')->nullable()->constrained()->onDelete('set null');
            
            // Basic info
            $table->string('name');
            $table->string('sku')->unique();
            $table->text('description')->nullable();
            
            // Pricing (in cents)
            $table->unsignedBigInteger('price');
            $table->unsignedBigInteger('cost_price')->nullable();
            
            // Inventory
            $table->integer('stock_quantity')->default(0);
            $table->integer('minimum_stock')->default(10);
            
            // Status
            $table->enum('status', ['ACTIVE', 'INACTIVE', 'ARCHIVED'])->default('INACTIVE');
            $table->boolean('is_featured')->default(false);
            
            // Metadata
            $table->json('attributes')->nullable();
            $table->json('tags')->nullable();
            
            // Dates
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index('sku');
            $table->index('user_id');
            $table->index('status');
            $table->index('created_at');
            $table->fullText('name', 'description');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
```

## Migration Rules

1. **Use UUID primary keys** - `$table->uuid('id')->primary()`
2. **Use foreignUuid()** - Not `foreignId()` for UUIDs
3. **Always use constrained()** - `foreignUuid('user_id')->constrained()`
4. **Add onDelete/onUpdate** - Specify cascade, restrict, set null, etc.
5. **Use nullable() explicitly** - For optional columns
6. **Add indexes** - For frequently queried columns
7. **Use enums** - Not hardcoded strings
8. **Use JSON for complex data** - Not separate tables
9. **Mirror defaults in models** - Match `$attributes` in Model
10. **One concern per migration** - Never mix DDL and DML
11. **Reversible down()** - Always provide rollback capability
12. **Never modify production migrations** - Create new ones instead

## Column Naming Conventions

```
Database           Laravel/PHP
user_id       →    $userId
created_at    →    $createdAt
is_active     →    $isActive
total_price   →    $totalPrice
first_name    →    $firstName
```

## Migration Organization

```
database/migrations/
├── 2024_01_15_120000_create_users_table.php
├── 2024_01_15_120100_create_roles_table.php
├── 2024_01_15_120200_create_role_user_table.php
├── 2024_01_16_090000_create_products_table.php
└── 2024_01_16_090100_create_orders_table.php
```

## Important Notes

- Never modify migrations that have run in production
- Use `Schema::disableForeignKeyConstraints()` only when necessary
- Test rollback functionality: `php artisan migrate:rollback`
- Generated migrations are fine but review and adjust as needed
