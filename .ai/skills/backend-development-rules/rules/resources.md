# Resource Implementation Rules

## Core Structure

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request  The current HTTP request
     * @return array The transformed resource array
     */
    public function toArray(Request $request): array
    {
        $role = $this->roles->first();

        return [
            'id' => $this->id ?? 'NA',
            'first_name' => $this->first_name ?? 'NA',
            'last_name' => $this->last_name ?? 'NA',
            'email' => $this->email ?? 'NA',
            'full_name' => $this->full_name ?? 'NA',
            
            'role' => $role ? [
                'name' => $role->name,
            ] : null,
            
            'agent' => $this->whenLoaded('agent', new AgentResource($this->agent)),
            'landlord' => $this->whenLoaded('landlord', new LandlordResource($this->landlord)),
        ];
    }
}
```

## Essential Rules

### 1. Use whenLoaded() for Relationships

```php
// ✅ CORRECT - Prevents N+1 queries
'agent' => $this->whenLoaded('agent', new AgentResource($this->agent))

// ❌ WRONG - Causes issues if not loaded
'agent' => new AgentResource($this->agent)
```

### 2. Access Relationships as Properties

```php
// ✅ CORRECT
$role = $this->roles->first();

// ❌ WRONG
$role = $this->roles()->first();
```

### 3. Nest Resources for Relationships

```php
// ✅ CORRECT
'agent' => new AgentResource($this->agent),
'addresses' => AddressResource::collection($this->addresses),

// ❌ WRONG
'agent' => $this->agent->toArray(),
'addresses' => $this->addresses->map(fn($a) => $a->toArray()),
```

### 4. Transform Data Appropriately

```php
// ✅ CORRECT - Transform dates, formats, etc.
return [
    'id' => $this->id,
    'name' => $this->name,
    'email' => $this->email,
    'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
    'status' => $this->status->name,  // Enum to string
    'is_active' => (bool) $this->is_active,
    'price' => helpers()->moneyHelper()->fromMinor($this->price_in_cents, true),
];
```

### 5. Handle Missing Data Gracefully

```php
// ✅ CORRECT - Provide defaults
'first_name' => $this->first_name ?? 'N/A',
'middle_name' => $this->middle_name,
'full_name' => $this->getFullName(),

// ❌ WRONG - Can cause null errors
'first_name' => $this->first_name,
```

### 6. Keep All Resources in Http/Resources/

```
app/Http/Resources/
├── UserResource.php
├── ProductResource.php
├── OrderResource.php
├── AgentResource.php
└── LandlordResource.php
```

## Common Patterns

### Single Resource

```php
class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'sku' => $this->sku,
            'description' => $this->description,
            'price' => helpers()->moneyHelper()->fromMinor($this->price),
            'status' => $this->status->name,
            
            'category' => new CategoryResource($this->whenLoaded('category')),
            'images' => helpers()->imageHelper()->getImageCollectionUrls($this->images),
        ];
    }
}
```

### Collection Usage

```php
// In controller
public function index(): JsonResponse
{
    $products = $this->productService->getAll();
    
    return response()->json([
        'data' => ProductResource::collection($products),
        'pagination' => helpers()->queryableHelper()->getPagination($products),
    ]);
}
```

### Nested Resources

```php
class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            
            // Single nested resource
            'customer' => new UserResource($this->whenLoaded('customer')),
            
            // Collection of resources
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            
            // Conditional nested resource
            'invoice' => $this->when(
                $this->relationLoaded('invoice') && $this->invoice,
                new InvoiceResource($this->invoice)
            ),
        ];
    }
}
```

### Conditional Fields

```php
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            
            // Include if user is authenticated and owns the resource
            'phone' => $this->when(
                auth()->check() && auth()->id() === $this->id,
                $this->phone
            ),
            
            // Include if relationship is loaded
            'roles' => $this->whenLoaded('roles', fn() => 
                $this->roles->pluck('name')->toArray()
            ),
        ];
    }
}
```

### Transforming Dates

```php
class EventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'start_date' => $this->start_date?->format('Y-m-d H:i:s'),
            'end_date' => $this->end_date?->format('Y-m-d'),
            'created_at' => $this->created_at?->diffForHumans(),
        ];
    }
}
```

### Transforming Enums

```php
class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            
            // Enum to string
            'type' => $this->type->name,
            'status' => $this->status->value,
            
            // Custom transformation
            'category_label' => match($this->category) {
                'ELECTRONICS' => 'Electronics',
                'CLOTHING' => 'Clothing',
                default => 'Other',
            },
        ];
    }
}
```

### Using Helpers for Formatting

```php
class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            
            // Money conversion
            'price' => helpers()->moneyHelper()->fromMinor($this->price_in_cents, true),
            'cost' => helpers()->moneyHelper()->fromMinor($this->cost_in_cents),
            
            // Image URLs
            'image' => helpers()->imageHelper()->getImageUrl($this->image_path, $this->name),
            'gallery' => helpers()->imageHelper()->getImageCollectionUrls($this->gallery_paths),
        ];
    }
}
```

## Documentation

```php
/**
 * Transform the resource into an array.
 *
 * Transforms the User model into an API response, including
 * eager-loaded relationships and formatted field values.
 *
 * @param  Request  $request  The current HTTP request
 * @return array The transformed resource array with user details and relationships
 */
public function toArray(Request $request): array
{
    // Implementation
}
```

## Code Quality Rules

1. **Use whenLoaded()** - For relationships
2. **Access as properties** - Not callable relationships
3. **Nest resources** - For related resources
4. **Transform data** - Format dates, enums, money
5. **Provide defaults** - Handle missing data
6. **Keep in Http/Resources/** - Single location
7. **Document methods** - PHPDoc blocks
8. **No business logic** - Only transformation

## Response Structure Guidelines

```php
// Single resource response
[
    'data' => [...],
    'pagination' => [...]  // If paginated
]

// Collection response
[
    'data' => [
        [...],
        [...],
    ],
    'pagination' => [...]
]

// Error response (from service)
[
    'code' => 401,
    'message' => 'Error message',
    'error' => 'Detailed error'  // Optional
]
```
