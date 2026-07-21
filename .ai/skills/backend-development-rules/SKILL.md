---
name: backend-development-rules
description: "Backend development patterns and rules for this application. Covers repository-service pattern, controller/service/repository structure, form requests, resources, models, migrations, helpers, testing, and code quality standards. Use when implementing backend features, creating repositories and services, defining models, writing controllers, or reviewing code structure. Ensures consistency with the application's established architecture."
license: MIT
metadata:
  author: dev L0N3LY
  version: 1.0
---

# Backend Development Rules

Comprehensive backend development patterns and rules for this application using Laravel's Repository-Service pattern.

## Overview

This application uses the **L0n3ly Laravel Repository With Service** package with automatic binding. All rules are organized by layer and component.

### Quick Start
- Repository/Service pattern → `rules/repository-service-pattern.md`
- Creating controllers → `rules/controllers.md`
- Creating services → `rules/services.md`
- Creating repositories → `rules/repositories.md`
- Working with models → `rules/models.md`
- Form validation → `rules/form-requests.md`
- API responses → `rules/resources.md`
- Enums (non-backed default) → `rules/enums.md`
- Response codes enum → `rules/response-code-enum.md`
- Testing → `rules/testing.md`
- Code quality → `rules/code-quality.md`
- PermissionHelper usage → `rules/permission-helper.md`
- QueryableHelper usage → `rules/queryable-helper.md`
- StringSearch usage → `rules/string-search-helper.md`

## Architecture Principles

### Separation of Concerns
- **Controllers**: HTTP requests/responses only
- **Services**: Business logic, uses repositories
- **Repositories**: Data access layer only
- **Form Requests**: Validation only
- **Resources**: Transform models for APIs
- **Models**: Eloquent models with relationships
- **Helpers**: Reusable utility functions
- **Enums**: Application constants

### Directory Structure
```
app/
├── Enums/
├── Http/Controllers/
├── Http/Requests/
├── Http/Resources/
├── Http/Middleware/
├── Services/
├── Repositories/
├── Helpers/
└── Models/
```

## How to Apply These Rules

1. **Identify what you're building** - Repository, Service, Controller, Model, etc.
2. **Read the relevant rule file** - Start with the layer you're working on
3. **Check existing code** - Always follow patterns already in the codebase first
4. **Follow naming conventions** - Interface/Implementation patterns are strict
5. **Add proper documentation** - PHPDoc blocks required for all methods
6. **Use ResponseCode enum** - Never hardcode HTTP status codes
7. **Test thoroughly** - Write feature tests for all endpoints

## Key Patterns

### Repository-Service Pattern
- Interface: `{Name}Repository` / `{Name}Service`
- Implementation: `{Name}RepositoryImplement` / `{Name}ServiceImplement`
- Auto-binding via `RepositoryAutoBindProvider` (no manual binding needed)

### Service Response Chain
```php
$this->setCode(ResponseCode::SUCCESS->value)
    ->setMessage('Operation successful')
    ->setData(['key' => 'value'])
    ->toJson();  // Required for Scribe documentation
```

### Controller Dependency Injection
```php
public function __construct(protected AuthService $authService) {}
```

### Model UUID Primary Keys
```php
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class User extends Model {
    use HasUuids;
    protected $keyType = 'string';
    public $incrementing = false;
}
```

## Code Quality Rules

1. **No inline comments** - Use PHPDoc blocks instead
2. **Type hints in implementations** - Not in interfaces
3. **Descriptive names** - `$user`, `$property` not `$u`, `$p`
4. **Focus methods** - One responsibility each
5. **Docblocks required** - All methods and constructors
6. **PSR-12 formatting** - Use Laravel Pint

## Consistency First

Before applying any rule:
1. Check what the codebase already does
2. If a pattern exists, follow it
3. Don't introduce multiple ways to do the same thing
4. Inconsistency is worse than a suboptimal pattern

## Response Codes

```php
ResponseCode::CREATED = 201              // Resource created
ResponseCode::SUCCESS = 200              // Operation successful
ResponseCode::BAD_REQUEST = 400          // Invalid request data
ResponseCode::UNAUTHORIZED = 401         // Authentication required
ResponseCode::FORBIDDEN = 403            // Permission denied
ResponseCode::NOT_FOUND = 404            // Resource not found
ResponseCode::VALIDATION_ERROR = 422     // Validation failed
ResponseCode::SERVER_ERROR = 500         // Internal server error
```

## Common Helpers

**⚠️ CRITICAL: The rule files contain EXACT implementations. Copy them verbatim. Never modify.**

- **PermissionHelper** → `rules/permission-helper.md` (EXACT IMPLEMENTATION)
  - `validatePermission()` - Check permissions, throw exception if denied
  - Copy the complete code block exactly from the rule file

- **QueryableHelper** → `rules/queryable-helper.md` (EXACT IMPLEMENTATION)
  - `fetchWithFilters()` - Paginate, search, filter, sort
  - `getPagination()` - Extract pagination metadata
  - Copy the complete code block exactly from the rule file

- **StringSearch** → `rules/string-search-helper.md` (EXACT IMPLEMENTATION)
  - `matchesWithFuzzyPrefix()` - Three-strategy fuzzy matching (substring → word-prefix → fuzzy)
  - `fuzzyMatches()` - Simple fuzzy character-order matching
  - `fuzzyScore()` - Get match quality score (0.0–1.0)
  - Copy the complete code block exactly from the rule file

- **MoneyHelper**
  - `toMinor()` - Convert dollars to cents (19.99 → 1999)
  - `fromMinor()` - Convert cents to dollars (1999 → 19.99)

- **ImageHelper**
  - `getImageUrl()` - Get image URL with fallback
  - `deleteImage()` - Delete image from storage

## Next Steps

1. Read the relevant rule file for your task
2. Review sibling files in the codebase
3. Follow established patterns first
4. Use Laravel Boost tools (`search-docs`, `database-schema`, etc.)
5. Write tests before finalizing

For syntax details and version-specific APIs, use `search-docs` tool.
