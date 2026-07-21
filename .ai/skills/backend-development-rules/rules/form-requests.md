# Form Request Implementation Rules

## Core Structure

```php
<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'The email address is required.',
            'email.email' => 'Please provide a valid email address.',
            'password.required' => 'The password is required.',
        ];
    }
}
```

## Essential Rules

### 1. Use Array Syntax for Validation

```php
// ✅ CORRECT - Array syntax
public function rules(): array
{
    return [
        'email' => ['required', 'string', 'email'],
        'password' => ['required', 'string', 'min:6'],
    ];
}

// ❌ WRONG - Pipe syntax (old style)
public function rules(): array
{
    return [
        'email' => 'required|string|email',
        'password' => 'required|string|min:6',
    ];
}
```

### 2. Always Implement messages()

```php
public function messages(): array
{
    return [
        'email.required' => 'The email address is required.',
        'email.email' => 'Please provide a valid email address.',
        'password.required' => 'The password is required.',
        'password.min' => 'Password must be at least 6 characters.',
    ];
}
```

### 3. Name Files After Action

```
RegisterRequest.php          // Register users
LoginRequest.php             // Login users
StoreProductRequest.php      // Create products
UpdateProductRequest.php     // Update products
StoreUserRequest.php         // Create users
UpdateUserRequest.php        // Update users
```

### 4. Keep Validation Simple

```php
// ✅ CORRECT - Simple rules in request
public function rules(): array
{
    return [
        'email' => ['required', 'email', 'unique:users,email'],
        'password' => ['required', 'string', 'min:8'],
    ];
}

// Complex validation belongs in service
public function validateBusinessLogic()
{
    // In service, not in request
    if ($this->userAlreadyHasRole()) {
        throw new Exception('...');
    }
}
```

### 5. Organize by Feature

```
app/Http/Requests/
├── Auth/
│   ├── RegisterRequest.php
│   ├── LoginRequest.php
│   └── UpdateProfileRequest.php
├── Product/
│   ├── StoreProductRequest.php
│   ├── UpdateProductRequest.php
│   └── FilterProductsRequest.php
└── Order/
    ├── StoreOrderRequest.php
    └── UpdateOrderRequest.php
```

## Common Validation Rules

```php
// Presence
'field' => ['required']           // Field required
'field' => ['required_if:other,value']
'field' => ['required_unless:other,value']
'field' => ['required_with:other,fields']

// String validation
'email' => ['string', 'email']
'url' => ['url']
'date' => ['date', 'date_format:Y-m-d']

// Uniqueness
'email' => ['unique:users']
'email' => ['unique:users,email,except:' . auth()->id()]
'sku' => ['unique:products,sku']

// Size/Length
'name' => ['string', 'min:3', 'max:255']
'age' => ['integer', 'min:18', 'max:100']
'file' => ['file', 'max:5120']  // 5MB

// File uploads
'avatar' => ['image', 'mimes:jpeg,png', 'max:5120']
'document' => ['file', 'mimes:pdf,doc,docx', 'max:10240']

// Relationships
'role_id' => ['required', 'exists:roles,id']
'user_ids' => ['array', 'exists:users,id']

// Enums
'status' => ['in:ACTIVE,INACTIVE,PENDING']

// Custom rules
'field' => ['required', Rule::unique('table').where('column', $value)]
```

## Conditional Validation

```php
public function rules(): array
{
    return [
        'user_type' => ['required', 'in:individual,business'],
        
        // Individual fields
        'first_name' => ['required_if:user_type,individual', 'string'],
        'last_name' => ['required_if:user_type,individual', 'string'],
        
        // Business fields
        'company_name' => ['required_if:user_type,business', 'string'],
        'company_number' => ['required_if:user_type,business', 'string'],
    ];
}
```

## Relationships Validation

```php
public function rules(): array
{
    return [
        'email' => ['required', 'email', 'unique:users'],
        'role_ids' => ['required', 'array', 'min:1'],
        'role_ids.*' => ['exists:roles,id'],
    ];
}
```

## Custom Error Messages

```php
public function messages(): array
{
    return [
        'email.required' => 'The email address is required.',
        'email.email' => 'Please provide a valid email address.',
        'email.unique' => 'This email is already registered.',
        
        'password.required' => 'The password is required.',
        'password.min' => 'Password must be at least 8 characters.',
        'password.regex' => 'Password must contain uppercase, lowercase, and numbers.',
        
        'role_ids.required' => 'At least one role must be assigned.',
        'role_ids.*.exists' => 'One or more selected roles do not exist.',
        
        'avatar.image' => 'Avatar must be a valid image.',
        'avatar.max' => 'Avatar size cannot exceed 5MB.',
    ];
}
```

## Authorization in Requests

```php
public function authorize(): bool
{
    // Return true for public endpoints
    return true;
}

// For protected endpoints
public function authorize(): bool
{
    // Check if user can perform this action
    return auth()->check();
}

// For specific resource authorization
public function authorize(): bool
{
    return auth()->user()?->can('update', $this->resource);
}
```

## Data Preparation

```php
public function prepareForValidation(): void
{
    $this->merge([
        'slug' => Str::slug($this->input('name')),
        'email' => strtolower($this->input('email')),
    ]);
}
```

## Using in Controller

```php
public function store(StoreProductRequest $request): JsonResponse
{
    // $request is already validated
    $validated = $request->validated();
    
    return $this->productService->create($request)->toJson();
}
```

## Code Quality Rules

1. **Always implement messages()** - User-friendly error messages
2. **Array syntax only** - Never use pipe syntax
3. **One request per action** - `StoreProductRequest`, `UpdateProductRequest`
4. **Keep it simple** - Complex validation in services
5. **Organize by feature** - Group in directories
6. **Document rules** - Use clear, descriptive messages
7. **Reuse validation** - Share rules across related requests
