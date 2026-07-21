# Testing Implementation Rules

## Feature Test Structure

```php
<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test user can login with valid credentials.
     */
    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
        ]);

        $response = $this->postJson('/api/sessions', [
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'code',
                'message',
                'data' => [
                    'user' => [
                        'id',
                        'email',
                        'first_name',
                        'last_name',
                    ],
                    'token',
                ],
            ]);
    }

    /**
     * Test user cannot login with invalid credentials.
     */
    public function test_user_cannot_login_with_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
        ]);

        $response = $this->postJson('/api/sessions', [
            'email' => 'test@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'code' => 401,
                'message' => 'The provided credentials are incorrect.',
            ]);
    }
}
```

## Testing Traits

### RefreshDatabase

Cleans and migrates database before each test:

```php
use Illuminate\Foundation\Testing\RefreshDatabase;

class LoginTest extends TestCase
{
    use RefreshDatabase;
    
    // Each test gets fresh database
}
```

## Test Naming Convention

Tests should clearly describe what they test:

```php
// ✅ CORRECT - Clear and specific
test_user_can_login_with_valid_credentials()
test_user_cannot_login_with_invalid_credentials()
test_user_cannot_login_if_account_is_inactive()
test_password_must_be_at_least_8_characters()

// ❌ WRONG - Vague and unclear
test_login()
test_validation()
test_user_works()
```

## Factory Usage

```php
// Create single record
$user = User::factory()->create([
    'email' => 'test@example.com',
    'status' => 'ACTIVE',
]);

// Create multiple records
$users = User::factory()->count(5)->create();

// Using factory states
$user = User::factory()
    ->admin()  // Custom state
    ->create();

// With relationships
$user = User::factory()
    ->has(Agent::factory()->count(1), 'agent')
    ->create();
```

## API Testing

### Making Requests

```php
// GET request
$response = $this->getJson('/api/users');

// POST request
$response = $this->postJson('/api/users', [
    'name' => 'John Doe',
    'email' => 'john@example.com',
]);

// PUT request
$response = $this->putJson('/api/users/123', [
    'name' => 'Jane Doe',
]);

// DELETE request
$response = $this->deleteJson('/api/users/123');
```

### Authenticated Requests

```php
$user = User::factory()->create();

$response = $this->actingAs($user)
    ->getJson('/api/profile');

// Or with token
$token = $user->createToken('test')->plainTextToken;

$response = $this->withHeader('Authorization', "Bearer {$token}")
    ->getJson('/api/profile');
```

## Assertions

### Status Assertions

```php
$response->assertStatus(200);
$response->assertStatus(201);
$response->assertStatus(404);
$response->assertStatus(401);
$response->assertStatus(403);
$response->assertStatus(422);
$response->assertStatus(500);

// Or shortcuts
$response->assertOk();                // 200
$response->assertCreated();           // 201
$response->assertNotFound();          // 404
$response->assertUnauthorized();      // 401
$response->assertForbidden();         // 403
```

### JSON Assertions

```php
$response->assertJson([
    'code' => 200,
    'message' => 'Success',
]);

// Assert JSON structure (doesn't check values)
$response->assertJsonStructure([
    'code',
    'message',
    'data' => [
        'user' => [
            'id',
            'email',
        ],
    ],
]);

// Assert JSON path
$response->assertJsonPath('data.user.email', 'test@example.com');

// Assert JSON has key
$response->assertJsonHasPath('data.user');

// Assert collection count
$response->assertJsonCount(5, 'data');
```

### Model Assertions

```php
// Assert model exists in database
$this->assertModelExists($user);

// Assert model count
$this->assertDatabaseCount('users', 5);

// Assert database has record
$this->assertDatabaseHas('users', [
    'email' => 'test@example.com',
]);

// Assert database missing record
$this->assertDatabaseMissing('users', [
    'email' => 'deleted@example.com',
]);
```

## Test Organization

### By Feature/Domain

```
tests/Feature/
├── Auth/
│   ├── LoginTest.php
│   ├── RegisterTest.php
│   └── LogoutTest.php
├── Product/
│   ├── CreateProductTest.php
│   ├── UpdateProductTest.php
│   ├── DeleteProductTest.php
│   └── ListProductsTest.php
└── Order/
    ├── CreateOrderTest.php
    └── ListOrdersTest.php
```

### By User Type

```
tests/Feature/
├── App/
│   ├── Auth/
│   └── Product/
└── Admin/
    ├── Auth/
    └── Product/
```

## Common Test Patterns

### Happy Path Test

```php
public function test_user_can_create_product(): void
{
    $user = User::factory()->admin()->create();

    $response = $this->actingAs($user)
        ->postJson('/api/products', [
            'name' => 'Laptop Pro',
            'sku' => 'LP-2024-001',
            'price' => 999.99,
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.name', 'Laptop Pro');
}
```

### Error Path Test

```php
public function test_user_cannot_create_product_without_permission(): void
{
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->postJson('/api/products', [
            'name' => 'Laptop Pro',
        ]);

    $response->assertStatus(403)
        ->assertJson([
            'code' => 403,
            'message' => 'Access denied.',
        ]);
}
```

### Validation Test

```php
public function test_product_name_is_required(): void
{
    $user = User::factory()->admin()->create();

    $response = $this->actingAs($user)
        ->postJson('/api/products', [
            'sku' => 'LP-2024-001',
            'price' => 999.99,
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['name']);
}
```

### Relationship Test

```php
public function test_user_includes_roles_in_response(): void
{
    $user = User::factory()
        ->has(Role::factory()->count(2), 'roles')
        ->create();

    $response = $this->actingAs($user)
        ->getJson('/api/profile');

    $response->assertStatus(200)
        ->assertJsonPath('data.roles', fn($roles) => count($roles) === 2);
}
```

## Test Coverage

Write tests covering:

1. **Happy Path** - Normal successful operation
2. **Failure Paths** - Error scenarios
3. **Edge Cases** - Boundary conditions
4. **Validation** - Form validation rules
5. **Authorization** - Permission checks
6. **Relationships** - Related data handling
7. **Pagination** - If applicable

## Test Rules

1. **Use RefreshDatabase** - Clean database per test
2. **Use factories** - Never hardcode test data
3. **Descriptive names** - Test name explains what it tests
4. **One assertion** - One logical assertion per test (multiple syntactical allowed)
5. **Test both paths** - Success and failure scenarios
6. **Organize by feature** - Group related tests
7. **Use route() helper** - Don't hardcode URLs
8. **Use meaningful data** - Realistic test values
9. **Test edge cases** - Boundary conditions
10. **Clean assertions** - Clear and specific

## Running Tests

```bash
# Run all tests
php artisan test

# Run specific test file
php artisan test tests/Feature/Auth/LoginTest.php

# Run specific test method
php artisan test --filter=test_user_can_login_with_valid_credentials

# Run with compact output
php artisan test --compact

# Run with coverage
php artisan test --coverage
```
