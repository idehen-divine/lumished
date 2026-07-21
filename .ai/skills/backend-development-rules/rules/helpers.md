# Helper Classes Rules

Helper classes provide reusable utility functions across the application. They extend the `L0n3ly\LaravelDynamicHelpers\Helper` base class and are accessed via the global `helpers()` function.

## Core Structure

```php
<?php

namespace App\Helpers;

use L0n3ly\LaravelDynamicHelpers\Helper;

class CustomHelper extends Helper
{
    /**
     * Perform an operation.
     *
     * Description of what this method does and why.
     *
     * @param  string  $value  The input value
     * @return string The processed value
     */
    public static function process(string $value): string
    {
        // Implementation
        return $value;
    }
}
```

## Location & Organization

- All helpers in `app/Helpers/`
- Extend `L0n3ly\LaravelDynamicHelpers\Helper`
- Access via `helpers()->helperName()->methodName()`
- One responsibility per helper class
- Static methods for stateless utilities

## Usage Pattern

```php
// Access helper methods
helpers()->permissionHelper()->validatePermission($permission);

// Use static methods directly
$minorAmount = MoneyHelper::toMinor(19.99);
```

## Common Helpers

### PermissionHelper

Validates user permissions and throws exceptions for unauthorized access.

```php
class PermissionHelper extends Helper
{
    /**
     * Validate if the authenticated user has the specified permission.
     *
     * @param  PermissionEnum  $permission  The required permission
     * @throws AccessDeniedHttpException
     * @throws UnauthorizedHttpException
     */
    public function validatePermission(PermissionEnum $permission): void
    {
        $user = auth()->user();
        
        if (!$user) {
            throw new UnauthorizedHttpException('', 'User not authenticated');
        }

        if (!$user->hasPermission($permission)) {
            throw new AccessDeniedHttpException('Access denied.');
        }
    }
}
```

**Usage in Service:**
```php
public function getAllProducts()
{
    try {
        helpers()->permissionHelper()->validatePermission(
            PermissionEnum::VIEW_PRODUCTS
        );
        
        // ... rest of method
    } catch (AccessDeniedHttpException $e) {
        return $this->setCode(ResponseCode::FORBIDDEN->value)
            ->setMessage('Access denied.');
    }
}
```

### QueryableHelper

Handles pagination, filtering, searching, and sorting for queries.

```php
class QueryableHelper extends Helper
{
    /**
     * Get pagination details from a paginated model.
     *
     * @param  \Illuminate\Contracts\Pagination\LengthAwarePaginator  $model
     * @return array Pagination information
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

    /**
     * Apply common filters like search, status, sorting, pagination.
     *
     * @param  Model|EloquentBuilder|QueryBuilder  $model
     * @param  callable|null  $extraQuery
     * @return LengthAwarePaginator Results with pagination
     */
    public function fetchWithFilters(
        $model,
        ?callable $extraQuery = null
    ): LengthAwarePaginator {
        // Applies search, status, sorting, pagination
    }
}
```

**Usage in Repository:**
```php
public function getAllProducts(): LengthAwarePaginator
{
    $query = $this->model->with('categories');
    
    return helpers()->queryableHelper()->fetchWithFilters($query);
}
```

**Usage in Service:**
```php
public function getAllProducts()
{
    $products = $this->mainRepository->getAllProducts();

    return $this->setCode(ResponseCode::SUCCESS->value)
        ->setData([
            'products' => ProductResource::collection($products),
            'pagination' => helpers()->queryableHelper()->getPagination($products),
        ]);
}
```

### MoneyHelper

Converts between major and minor currency units (dollars ↔ cents).

```php
class MoneyHelper extends Helper
{
    /**
     * Convert major currency amount to minor unit.
     * 
     * Example: 19.99 → 1999
     *
     * @param  int|float|null  $amount  The amount in major units
     * @return int|null The amount in minor units
     */
    public static function toMinor(int|float|null $amount = null): ?int
    {
        if (is_null($amount) || !is_numeric($amount) || $amount < 0) {
            return null;
        }

        return (int) round($amount * 100);
    }

    /**
     * Convert minor currency unit to major amount.
     * 
     * Example: 1999 → 19.99
     *
     * @param  int|float|string|null  $amount  The amount in minor units
     * @param  bool  $format  Whether to format with currency symbol
     * @return float|string|null The amount in major units
     */
    public static function fromMinor(
        int|float|string|null $amount = null, 
        $format = false
    ): ?float|string {
        if (is_null($amount) || !is_numeric($amount) || $amount < 0) {
            return null;
        }

        $amount = round($amount / 100, 2);
        
        if ($format) {
            return self::addCurrency($amount);
        }

        return $amount;
    }
}
```

**Usage in Resource:**
```php
public function toArray(Request $request): array
{
    return [
        'cost_price' => helpers()->moneyHelper()->fromMinor($this->cost_price, true),
        'selling_price' => helpers()->moneyHelper()->fromMinor($this->selling_price, true),
    ];
}
```

### ImageHelper

Handles image URL generation with fallbacks and deletion.

```php
class ImageHelper extends Helper
{
    /**
     * Get the full URL for an image path with fallback placeholder.
     *
     * @param  string|null  $path  The image path
     * @param  string  $title  Title for placeholder if image not found
     * @return string The image URL or placeholder URL
     */
    public function getImageUrl(?string $path, string $title): string
    {
        $encodedTitle = urlencode($title);

        if ($path && filter_var($path, FILTER_VALIDATE_URL)) {
            return $path;
        }

        if ($path && Storage::disk('public')->exists($path)) {
            return Storage::url($path);
        }

        return "https://placehold.co/800x800/d5d5d5/000000?text={$encodedTitle}";
    }

    /**
     * Get full URLs for a collection of image paths.
     *
     * @param  array|null  $paths  Array of image paths
     * @return array Array of valid image URLs
     */
    public function getImageCollectionUrls(?array $paths): array
    {
        // Returns array of valid image URLs
    }

    /**
     * Delete an image from storage.
     *
     * @param  string|null  $path  The image path to delete
     * @return bool Whether deletion was successful
     */
    public function deleteImage(?string $path): bool
    {
        // Deletes image if exists
    }
}
```

**Usage in Resource:**
```php
public function toArray(Request $request): array
{
    return [
        'image_path' => helpers()->imageHelper()->getImageUrl($this->image_path, $this->name),
        'gallery_images_path' => helpers()->imageHelper()->getImageCollectionUrls($this->gallery_images_path),
    ];
}
```

**Usage in Service:**
```php
public function updateProductImage(string $id, array $data)
{
    // Delete old images before updating
    if (!empty($data['image_path'])) {
        helpers()->imageHelper()->deleteImage($product->image_path);
    }
}
```

## Creating Custom Helpers

```php
<?php

namespace App\Helpers;

use L0n3ly\LaravelDynamicHelpers\Helper;

class CustomHelper extends Helper
{
    /**
     * Description of the helper method.
     *
     * Detailed explanation of what this method does,
     * why it's useful, and any important behaviors.
     *
     * @param  string  $parameter  Description of parameter
     * @return string The result of the operation
     */
    public static function methodName(string $parameter): string
    {
        // Implementation
        return $parameter;
    }
}
```

## Helper Rules

1. **Extend Helper base class** - From `L0n3ly\LaravelDynamicHelpers\Helper`
2. **Single responsibility** - One focused purpose per helper
3. **Use static methods** - For stateless utilities
4. **Null-safe operations** - Handle null inputs gracefully
5. **Document all methods** - Comprehensive PHPDoc blocks
6. **Place in app/Helpers/** - Consistent location
7. **Access via helpers()** - Use the global function
8. **Test thoroughly** - Write tests for helper functions

## Usage Examples

### Permission Validation

```php
helpers()->permissionHelper()->validatePermission(PermissionEnum::DELETE_USER);
```

### Pagination

```php
$pagination = helpers()->queryableHelper()->getPagination($results);
```

### Money Conversion

```php
$cents = MoneyHelper::toMinor(19.99);      // 1999
$dollars = MoneyHelper::fromMinor(1999);   // 19.99
```

### Image Handling

```php
$url = helpers()->imageHelper()->getImageUrl($path, $title);
helpers()->imageHelper()->deleteImage($path);
```
