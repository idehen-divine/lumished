# QueryableHelper Implementation Rules

The QueryableHelper provides a standardized way to handle pagination, filtering, searching, and sorting across repositories.

⚠️ **CRITICAL: This is the authoritative implementation. Copy it exactly. Never modify without understanding all implications.**

## Core Purpose

- Standardize pagination output format
- Handle search/filter/sort/pagination parameters
- Prevent N+1 queries with eager loading
- Return consistent paginated responses

## Location & Setup

Helper class extends `L0n3ly\LaravelDynamicHelpers\Helper` and lives in `app/Helpers/`:

```php
<?php

namespace App\Helpers;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Schema;
use L0n3ly\LaravelDynamicHelpers\Helper;

class QueryableHelper extends Helper
{
    // Implementation methods
}
```

The helper provides:
- Opt-in filtering (status, featured, exact filters, search)
- Configurable sorting with column mapping
- Automatic request validation
- Fuzzy search via StringSearch helper
- Consistent pagination output format

## Complete Implementation (EXACT - DO NOT MODIFY)

Copy this implementation exactly into `app/Helpers/QueryableHelper.php`. Do not change, remove, or add code:

```php
<?php

namespace App\Helpers;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Schema;
use L0n3ly\LaravelDynamicHelpers\Helper;

class QueryableHelper extends Helper
{
    /**
     * Get pagination details from a paginated model.
     *
     * Extracts pagination metadata including from, to, total, per_page,
     * first_page, previous_page, current_page, next_page, and last_page.
     *
     * @param  \Illuminate\Pagination\LengthAwarePaginator  $paginator  The paginated result
     * @return array Pagination information
     */
    public function getPagination(LengthAwarePaginator $paginator): array
    {
        return [
            'from'          => $paginator->firstItem(),
            'to'            => $paginator->lastItem(),
            'total'         => $paginator->total(),
            'per_page'      => $paginator->perPage(),
            'first_page'    => 1,
            'previous_page' => $paginator->currentPage() > 1 ? $paginator->currentPage() - 1 : null,
            'current_page'  => $paginator->currentPage(),
            'next_page'     => $paginator->currentPage() < $paginator->lastPage() ? $paginator->currentPage() + 1 : null,
            'last_page'     => $paginator->lastPage(),
        ];
    }

    /**
     * Apply filters, search, sorting, and pagination to a query.
     *
     * Applies opt-in filters based on options array. Validates all request parameters.
     * Supports: exact filters, status filter, featured filter, fuzzy search, sorting, pagination.
     * Returns paginated results with query string preserved.
     *
     * @param  Model|EloquentBuilder|QueryBuilder  $model  The model/query to filter
     * @param  array  $options  Configuration array (see examples below)
     * @param  callable|null  $extraQuery  Optional callback for additional query modifications
     * @return \Illuminate\Pagination\LengthAwarePaginator Paginated results
     */
    public function fetchWithFilters(
        Model|EloquentBuilder|QueryBuilder $model,
        array $options = [],
        ?callable $extraQuery = null,
    ): LengthAwarePaginator {
        $this->validate($options);

        $query = $model instanceof Model ? $model->newQuery() : $model;

        // ── Status filter (opt-in via options) ──────────────────────────────
        if (isset($options['status_column'])) {
            $statusColumn = (string) $options['status_column'];
            if (request()->filled('status') && $this->hasColumn($query, $statusColumn)) {
                $query->where($statusColumn, request('status') === 'active');
            }
        }

        // ── Featured filter (opt-in via options) ────────────────────────────
        if (isset($options['featured_column'])) {
            $featuredColumn = (string) $options['featured_column'];
            if (request()->filled('featured') && $this->hasColumn($query, $featuredColumn)) {
                $query->where($featuredColumn, request('featured') === 'featured');
            }
        }

        // ── Exact filters (opt-in via options) ──────────────────────────────
        foreach ($options['exact_filters'] ?? [] as $requestKey => $column) {
            $key = is_int($requestKey) ? $column : $requestKey;
            if (request()->filled($key) && $this->hasColumn($query, (string) $column)) {
                $query->where((string) $column, request()->input($key));
            }
        }

        // ── Fuzzy search (opt-in via options) ───────────────────────────────
        $searchable = $options['searchable'] ?? [];
        if (request()->filled('search') && $searchable !== []) {
            $search = (string) request()->input('search');

            $allResults = $query->get();
            $filtered   = $allResults->filter(function (Model $record) use ($search, $searchable): bool {
                foreach ($searchable as $column) {
                    $value = (string) ($record->{$column} ?? '');
                    if (helpers()->stringSearch()->matchesWithFuzzyPrefix($search, $value)) {
                        return true;
                    }
                }
                return false;
            });

            $query = $model instanceof Model ? $model->newQuery() : clone $model;

            if ($filtered->isNotEmpty()) {
                $query->whereIn('id', $filtered->pluck('id')->toArray());
            } else {
                $query->whereRaw('1=0');
            }
        }

        // ── Extra caller-supplied constraints ───────────────────────────────
        if ($extraQuery) {
            $extraQuery($query);
        }

        // ── Sorting ─────────────────────────────────────────────────────────
        $sortBy    = request()->input('sort_by');
        $sortOrder = request()->input('sort_order', (string) ($options['default_sort_order'] ?? 'asc'));
        $sortMap   = $options['sort_map'] ?? [];

        if (is_string($sortBy) && $sortBy !== '') {
            $sortColumn = (string) ($sortMap[$sortBy] ?? $sortBy);
            if ($this->hasColumn($query, $sortColumn)) {
                $query->orderBy($sortColumn, $sortOrder);
            }
        } elseif (! empty($options['default_sort_by'])) {
            $defaultSortBy = (string) $options['default_sort_by'];
            if ($this->hasColumn($query, $defaultSortBy)) {
                $query->orderBy($defaultSortBy, $sortOrder);
            }
        }

        // ── Pagination ──────────────────────────────────────────────────────
        $perPageDefault = (int) ($options['per_page_default'] ?? 10);
        $perPageMax     = (int) ($options['per_page_max'] ?? 100);
        $perPage        = min(max((int) request()->input('per_page', $perPageDefault), 1), $perPageMax);

        return $query->paginate($perPage)->withQueryString();
    }

    /**
     * Validate request parameters according to options configuration.
     *
     * Builds validation rules dynamically based on options provided.
     * Only validates parameters that were configured.
     *
     * @param  array  $options  Configuration array with filter/sort options
     * @return void Throws ValidationException if validation fails
     */
    public function validate(array $options = []): void
    {
        $sortable      = $options['sortable'] ?? [];
        $sortMap       = $options['sort_map'] ?? [];
        $sortByOptions = array_values(array_unique([...$sortable, ...array_keys($sortMap)]));
        $perPageMax    = (int) ($options['per_page_max'] ?? 100);

        $sortByRule = $sortByOptions === []
            ? ['nullable', 'string']
            : ['nullable', 'string', 'in:' . implode(',', $sortByOptions)];

        $rules = [
            'sort_by'    => $sortByRule,
            'sort_order' => ['nullable', 'string', 'in:asc,desc'],
            'per_page'   => ['nullable', 'integer', 'min:1', 'max:' . $perPageMax],
        ];

        // Only validate these params when the caller opted in
        if (isset($options['status_column'])) {
            $rules['status'] = ['nullable', 'string', 'in:active,inactive'];
        }

        if (isset($options['featured_column'])) {
            $rules['featured'] = ['nullable', 'string', 'in:featured,not-featured'];
        }

        if (! empty($options['searchable'])) {
            $rules['search'] = ['nullable', 'string', 'max:255'];
        }

        request()->validate($rules);
    }

    /**
     * Check if a column exists on the query's table.
     *
     * Handles both Eloquent and Query builders. Parses table aliases.
     * Returns false if column doesn't exist to prevent database errors.
     *
     * @param  EloquentBuilder|QueryBuilder  $query  The query builder instance
     * @param  string  $column  The column name to check
     * @return bool True if column exists, false otherwise
     */
    protected function hasColumn(EloquentBuilder|QueryBuilder $query, string $column): bool
    {
        $table = $query instanceof EloquentBuilder
            ? $query->getModel()->getTable()
            : $query->from;

        if (! is_string($table) || $table === '') {
            return false;
        }

        if (str_contains($table, ' as ')) {
            $table = trim(explode(' as ', $table)[0]);
        }

        return Schema::hasColumn($table, $column);
    }
}
```

### Implementation Notes:

**Do NOT modify this code. It is:**
- ✅ Production tested
- ✅ Handles all edge cases
- ✅ Integrates with StringSearch helper
- ✅ Validates all parameters
- ✅ Prevents SQL errors with hasColumn()
- ✅ Works with Model, EloquentBuilder, and QueryBuilder

**Key Implementation Details:**

1. **`getPagination()`** - Extracts 8 pagination values from LengthAwarePaginator
2. **`fetchWithFilters()`** - Main method, 7 filter phases in exact order
3. **`validate()`** - Builds dynamic rules based on what options enabled
4. **`hasColumn()`** - Safely checks columns exist before filtering (prevents errors)
5. **Fuzzy Search** - Fetches all results, filters in PHP with StringSearch helper, rebuilds query
6. **`withQueryString()`** - Preserves query parameters in pagination links

## Core Methods

### getPagination()

Extract pagination metadata from a paginated collection:

```php
/**
 * Get pagination details from a paginated model.
 *
 * Extracts pagination information including current page, total records,
 * next/previous pages, and per-page limits. Useful for API responses.
 *
 * @param  \Illuminate\Contracts\Pagination\LengthAwarePaginator  $model  The paginated model
 * @return array Pagination information with all navigation details
 */
public function getPagination($model): array
{
    return [
        'from' => $model->firstItem(),
        'to' => $model->lastItem(),
        'total' => $model->total(),
        'per_page' => $model->perPage(),
        'first_page' => 1,
        'previous_page' => $model->currentPage() > 1 
            ? $model->currentPage() - 1 
            : null,
        'current_page' => $model->currentPage(),
        'next_page' => $model->currentPage() < $model->lastPage() 
            ? $model->currentPage() + 1 
            : null,
        'last_page' => $model->lastPage(),
    ];
}
```

**Usage in Service:**
```php
$products = $this->mainRepository->getAllProducts();

return $this->setCode(ResponseCode::SUCCESS->value)
    ->setData([
        'products' => ProductResource::collection($products),
        'pagination' => helpers()->queryableHelper()->getPagination($products),
    ]);
```

### fetchWithFilters()

Apply configurable filters (search, status, featured, exact filters, sorting, pagination) to a query:

```php
/**
 * Apply filters like exact matching, fuzzy search, sorting, and pagination to a query.
 *
 * Provides opt-in filtering via options array. Only filters you specify in options
 * will be applied. Validates all request parameters according to options.
 * Returns paginated results with query string preserved.
 *
 * @param  Model|EloquentBuilder|QueryBuilder  $model  The base query/model to apply filters to
 * @param  array  $options  Configuration for filters (see below)
 * @param  callable|null  $extraQuery  Optional callback for additional query modifications
 * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator Paginated results
 */
public function fetchWithFilters(
    Model|EloquentBuilder|QueryBuilder $model,
    array $options = [],
    ?callable $extraQuery = null,
): LengthAwarePaginator
```

**Options Array Configuration:**

```php
[
    // Exact value filters (opt-in)
    'exact_filters' => [
        'order_type' => 'order_type',  // request key => column name
        'category' => 'category_id',
    ],
    
    // Status filter (boolean: active=1, inactive=0)
    'status_column' => 'status',  // or don't include to disable
    
    // Featured filter (boolean: featured=1, not-featured=0)
    'featured_column' => 'is_featured',  // or don't include to disable
    
    // Fuzzy search across columns (opt-in)
    'searchable' => ['name', 'description', 'email'],
    
    // Sorting configuration
    'sortable' => ['created_at', 'name', 'price'],
    'sort_map' => [
        'name' => 'first_name',  // Maps request sort_by to column
    ],
    'default_sort_by' => 'created_at',
    'default_sort_order' => 'desc',
    
    // Pagination configuration
    'per_page_default' => 15,
    'per_page_max' => 100,
]
```

## Usage in Repositories

### Basic with Exact Filters

```php
/**
 * Get all admin orders with filtering by order type and sorting.
 *
 * Applies exact filter for order_type, allows sorting by created_at or order_type,
 * and paginates results. Eager loads rider and vehicle relationships.
 *
 * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator Paginated orders
 */
public function getAllAdminOrders(): LengthAwarePaginator
{
    $query = $this->model->with(['rider', 'vehicle']);

    return helpers()->queryableHelper()->fetchWithFilters($query, [
        'exact_filters' => [
            'order_type' => 'order_type',
        ],
        'sortable' => ['created_at', 'order_type'],
        'default_sort_by' => 'created_at',
        'default_sort_order' => 'desc',
    ]);
}
```

### With Search and Status Filter

```php
/**
 * Get all products with search, status filter, and sorting.
 */
public function getAllProducts(): LengthAwarePaginator
{
    $query = $this->model->with('categories');

    return helpers()->queryableHelper()->fetchWithFilters($query, [
        'searchable' => ['name', 'description'],
        'status_column' => 'status',
        'sortable' => ['created_at', 'name', 'price'],
        'default_sort_by' => 'created_at',
        'default_sort_order' => 'desc',
        'per_page_default' => 20,
        'per_page_max' => 100,
    ]);
}
```

### With Multiple Filters and Sort Mapping

```php
/**
 * Get all users with multiple filter options and column mapping.
 */
public function getAllUsers(): LengthAwarePaginator
{
    $query = $this->model->with('roles');

    return helpers()->queryableHelper()->fetchWithFilters($query, [
        'searchable' => ['email', 'first_name', 'last_name'],
        'status_column' => 'status',
        'exact_filters' => [
            'role' => 'role_id',
            'department' => 'department_id',
        ],
        'sortable' => ['created_at', 'first_name', 'last_name', 'email'],
        'sort_map' => [
            'name' => 'first_name',
        ],
        'default_sort_by' => 'created_at',
        'default_sort_order' => 'desc',
    ]);
}
```

### With Extra Query Callback

```php
/**
 * Get all featured products that are active.
 */
public function getAllFeaturedProducts(): LengthAwarePaginator
{
    $query = $this->model->with('categories');

    return helpers()->queryableHelper()->fetchWithFilters(
        $query,
        [
            'searchable' => ['name', 'description'],
            'featured_column' => 'is_featured',
            'sortable' => ['created_at', 'name'],
            'default_sort_by' => 'created_at',
        ],
        fn($q) => $q->where('status', 'ACTIVE')
    );
}
```

## Usage in Services

### Standard Pattern

```php
/**
 * Get all products with pagination.
 */
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
            ->setMessage('Access denied. Missing required permission.');
    } catch (\Exception $e) {
        return $this->setCode(ResponseCode::SERVER_ERROR->value)
            ->setMessage('An error occurred while retrieving products')
            ->setError($e->getMessage());
    }
}
```

## Request Parameters

The helper reads parameters from the request URL. Only parameters you configure in options are accepted:

```
GET /api/orders?order_type=delivery&search=john&sort_by=created_at&sort_order=desc&per_page=25
```

| Parameter | Type | Default | Description | When Available |
|-----------|------|---------|-------------|-----------------|
| `search` | string | null | Fuzzy search term | When `searchable` is configured |
| `status` | string | null | Filter by status (active/inactive) | When `status_column` is configured |
| `featured` | string | null | Filter by featured (featured/not-featured) | When `featured_column` is configured |
| `{custom}` | mixed | null | Exact filter values | When defined in `exact_filters` |
| `sort_by` | string | default | Column to sort by (must be in `sortable`) | When `sortable` is configured |
| `sort_order` | string | asc | Sort direction (asc or desc) | Always available |
| `per_page` | int | 10 | Records per page (respects `per_page_max`) | Always available |
| `page` | int | 1 | Current page number | Always available |

## Pagination Response Format

The `getPagination()` method returns:

```php
[
    'from' => 1,              // First record number on current page
    'to' => 15,               // Last record number on current page
    'total' => 250,           // Total number of records
    'per_page' => 15,         // Records per page
    'first_page' => 1,        // Always 1
    'previous_page' => null,  // Previous page or null if on first page
    'current_page' => 1,      // Current page number
    'next_page' => 2,         // Next page or null if on last page
    'last_page' => 17,        // Total number of pages
]
```

## Common Patterns

### Opt-in Filters Only

Only the filters you explicitly configure in options are available. This is intentional to control what clients can filter/search:

```php
// ✅ CORRECT - Only allows exact filter by category
public function getAllProducts(): LengthAwarePaginator
{
    $query = $this->model->with('categories');
    return helpers()->queryableHelper()->fetchWithFilters($query, [
        'exact_filters' => ['category' => 'category_id'],
        'sortable' => ['created_at'],
    ]);
}

// ❌ WRONG - Don't enable all filters just because they exist
// Configure only what the API should allow
```

### Sort Mapping for Calculated/Related Columns

Map friendly request names to actual database columns:

```php
public function getAllOrders(): LengthAwarePaginator
{
    $query = $this->model->with(['user', 'items']);
    return helpers()->queryableHelper()->fetchWithFilters($query, [
        'sortable' => ['created_at', 'customer'],
        'sort_map' => [
            'customer' => 'user.first_name',  // Maps 'customer' request param
        ],
        'default_sort_by' => 'created_at',
    ]);
}
```

### Validation Error Handling

The helper calls `validate()` internally, so validation errors are returned automatically:

```php
// Request: GET /api/orders?per_page=999&sort_by=invalid
// Returns: 422 Validation Error
// - per_page must not exceed per_page_max
// - sort_by must be in the sortable list
```

The validation is automatic based on your configuration—no need to manually validate in the service.

### Fuzzy Search with StringSearch Helper

The helper uses fuzzy prefix matching for search:

```php
helpers()->stringSearch()->matchesWithFuzzyPrefix('john', 'Jonathan')  // true
helpers()->stringSearch()->matchesWithFuzzyPrefix('john', 'John Doe') // true
helpers()->stringSearch()->matchesWithFuzzyPrefix('john', 'Mike')     // false
```

## Code Quality Rules

1. **Use in repositories only** - Never in services
2. **Eager load relationships** - Always use `with()` on query before passing
3. **Return LengthAwarePaginator** - Type hint return as `LengthAwarePaginator`
4. **Extract pagination in service** - Call `getPagination()` in service, not repository
5. **Opt-in filters only** - Configure only filters the API should expose
6. **Document all options** - PHPDoc should list which filters are available
7. **Use sort_map** - Don't expose internal column names directly
8. **Validate early** - Let the helper validate; don't duplicate in service
9. **Use per_page_max** - Always set maximum records per page for performance
10. **Test parameter combinations** - Test search + filters + sort together

## Example API Endpoint

**Request:**
```
GET /api/admin/orders?order_type=delivery&search=john&sort_by=created_at&sort_order=desc&per_page=20
```

**Repository:**
```php
public function getAllAdminOrders(): LengthAwarePaginator
{
    $query = $this->model->with(['rider', 'vehicle']);
    
    return helpers()->queryableHelper()->fetchWithFilters($query, [
        'exact_filters' => ['order_type' => 'order_type'],
        'searchable' => ['description', 'notes'],
        'sortable' => ['created_at', 'order_type'],
        'default_sort_by' => 'created_at',
        'default_sort_order' => 'desc',
        'per_page_default' => 15,
        'per_page_max' => 100,
    ]);
}
```

**Service:**
```php
$orders = $this->orderRepository->getAllAdminOrders();

return $this->setCode(ResponseCode::SUCCESS->value)
    ->setData([
        'orders' => OrderResource::collection($orders),
        'pagination' => helpers()->queryableHelper()->getPagination($orders),
    ]);
```

**Response:**
```json
{
    "code": 200,
    "message": "success",
    "data": {
        "orders": [...],
        "pagination": {
            "from": 1,
            "to": 20,
            "total": 245,
            "per_page": 20,
            "current_page": 1,
            "last_page": 13,
            "next_page": 2,
            "previous_page": null
        }
    }
}
```
