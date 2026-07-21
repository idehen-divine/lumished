---
name: Backend Compliance Audit
description: Generic audit checklist for verifying any Laravel backend matches Repository-Service pattern rules. Portable across projects using L0n3ly Laravel Repository With Service.
type: reference
license: MIT
metadata:
  author: dev L0N3LY
  version: 1.0
---

# Backend Compliance Audit Checklist

**Purpose**: Verify backend implementation matches established development rules.

**Applicable To**: Any Laravel application with established backend development rules.

**Scope**: Repository-Service pattern, controllers, services, repositories, models, migrations, helpers, enums, form requests, resources, testing, and code quality.

**Frequency**: Run during code review, before PRs, and before releases.

---

## 1. CODE QUALITY & STANDARDS

### Formatting & Style
- [ ] Run `vendor/bin/pint --dirty --format agent` — all files comply
- [ ] Check PSR-12 compliance (naming, spacing, curly braces)
- [ ] No `dump()`, `dd()`, `var_dump()` in production code
- [ ] All control structures use curly braces (even single-line bodies)

### Type Hints & Documentation
- [ ] All method parameters have type hints
- [ ] All methods have explicit return type declarations
- [ ] All methods have PHPDoc blocks (no inline comments)
- [ ] PHPDoc includes `@param`, `@return`, `@throws` tags where needed
- [ ] Use nullable types (`?Model`) instead of `findOrFail()` in repositories

### PHP 8.3+ Features
- [ ] Constructor property promotion used: `public function __construct(public UserRepository $repo) {}`
- [ ] Named arguments used where applicable
- [ ] Nullsafe operator (`?->`) used appropriately

---

## 2. REPOSITORY PATTERN IMPLEMENTATION

### Repository Structure
- [ ] Each repository has an interface: `{Name}Repository`
- [ ] Each repository has implementation: `{Name}RepositoryImplement`
- [ ] Repository extends `Eloquent` from `L0n3ly\LaravelRepositoryWithService\Implementations\Eloquent`
- [ ] Repository implements matching interface
- [ ] Repository sets `$this->model` in constructor

### Data Access Best Practices
- [ ] Eager load relationships with `with()` to prevent N+1 queries
- [ ] Select only needed columns with `select()`
- [ ] Return models, not arrays
- [ ] Use query builder methods (`where()`, `with()`, `orderBy()`, etc.)
- [ ] Return nullable types (`?Model`) instead of throwing exceptions
- [ ] Load relationships before returning: `load(['relation1', 'relation2'])`

### Pagination & Filtering (if using QueryableHelper)
- [ ] Use `helpers()->queryableHelper()->fetchWithFilters($query, $options)` for paginated results
- [ ] Pass only explicitly configured filters in options array (opt-in approach)
- [ ] Never expose filters/columns that aren't explicitly configured

---

## 3. SERVICE LAYER IMPLEMENTATION

### Service Structure
- [ ] Each service has an interface: `{Name}Service`
- [ ] Each service has implementation: `{Name}ServiceImplement`
- [ ] Service injects repositories via constructor using property promotion
- [ ] Service uses appropriate response builder trait (e.g., `ResultService`)

### Business Logic & Permission Validation
- [ ] Permission check at method start (if using PermissionHelper)
- [ ] Use permission enums (not strings) for type safety
- [ ] Catch `AccessDeniedHttpException` separately (403)
- [ ] Catch `UnauthorizedHttpException` separately (401)
- [ ] Catch general `\Exception` for unexpected errors (500)
- [ ] Null check repository returns: `if (!$model) { return appropriate response }`

### Response Chain Pattern
- [ ] Use consistent response chain pattern across all methods
- [ ] Map HTTP status codes correctly (200, 201, 400, 401, 403, 404, 422, 500)
- [ ] Always include meaningful messages in responses
- [ ] Call appropriate serialization method for API documentation

---

## 4. CONTROLLERS & ROUTING

### Controller Responsibilities
- [ ] Controllers only handle HTTP request/response mapping
- [ ] All business logic delegated to services
- [ ] Dependency injection via constructor property promotion
- [ ] Controllers are thin (15-25 lines per method typical)

### Route Organization
- [ ] Routes use named routes (avoid hardcoding URLs)
- [ ] API routes organized by feature/domain
- [ ] Middleware applied correctly (auth, permission, throttle, etc.)
- [ ] HTTP methods match semantics (GET, POST, PUT/PATCH, DELETE)

---

## 5. MODELS & ELOQUENT

### Model Setup
- [ ] Models define `$table` if not following Laravel convention
- [ ] Models use appropriate ID type (UUID via `HasUuids` or auto-increment)
- [ ] Models define relationships correctly (`hasMany()`, `belongsTo()`, `belongsToMany()`)
- [ ] Models only define relationships, scopes, and attributes (no business logic)

### Relationships & Eager Loading
- [ ] Relationships properly documented in PHPDoc
- [ ] No N+1 queries — all relationships eager-loaded in repositories
- [ ] Soft deletes implemented consistently where needed
- [ ] Foreign keys with appropriate cascading (delete/update)

---

## 6. ENUMS & CONSTANTS

### Enum Implementation
- [ ] Enums follow project convention (backed vs non-backed)
- [ ] Enum cases use consistent naming (e.g., UPPER_SNAKE_CASE)
- [ ] Enum cases match database values (if backed)
- [ ] Permission/Role enums match database permission names

### Enum Usage
- [ ] Type-safe enum usage in validation and authorization
- [ ] Enums cast in models: `protected $casts = ['status' => StatusEnum::class]`
- [ ] Enum properties used correctly (e.g., `->name`, `->value`)

---

## 7. FORM REQUESTS & VALIDATION

### Form Request Structure
- [ ] Each endpoint has corresponding FormRequest class
- [ ] FormRequest validates all user input (never in controller)
- [ ] FormRequest implements `authorize()` method
- [ ] Validation rules comprehensive (min, max, unique, exists, etc.)
- [ ] Custom validation rules created when needed

### Validation Rules
- [ ] All required fields marked as `required`
- [ ] Field types validated (string, integer, email, etc.)
- [ ] String constraints applied (min, max length)
- [ ] Database uniqueness/existence verified where needed
- [ ] Nested array validation properly structured

---

## 8. API RESOURCES & RESPONSES

### Resource Implementation
- [ ] Each model has corresponding Resource class (for APIs)
- [ ] Resources transform model data correctly for frontend
- [ ] Resources conditionally expose sensitive data
- [ ] ResourceCollections properly implemented for lists
- [ ] Relationships eager-loaded before resource transformation

### Response Structure
- [ ] Consistent API response format across all endpoints
- [ ] Error responses properly structured and documented
- [ ] HTTP status codes semantically correct:
  - 200 SUCCESS (retrieve, update success)
  - 201 CREATED (resource created)
  - 400 BAD_REQUEST (invalid input format)
  - 401 UNAUTHORIZED (authentication required)
  - 403 FORBIDDEN (authenticated but lacking permission)
  - 404 NOT_FOUND (resource doesn't exist)
  - 422 VALIDATION_ERROR (validation failed)
  - 500 SERVER_ERROR (unexpected error)

---

## 9. HELPER IMPLEMENTATIONS (if using helpers)

### Helper Classes
- [ ] All helpers extend appropriate base class (e.g., `L0n3ly\LaravelDynamicHelpers\Helper`)
- [ ] Helpers located in `app/Helpers/`
- [ ] Helpers are stateless utility providers

### Required Helpers (if project defines them)
- [ ] PermissionHelper (or equivalent) — Permission/authorization validation
  - Used consistently across all protected operations
  - Throws appropriate exceptions with clear messages

- [ ] QueryableHelper (or equivalent) — Filtering, search, pagination
  - Used for all paginated list endpoints
  - Opt-in configuration prevents unintended exposure

- [ ] StringSearch (or equivalent) — Fuzzy matching/search
  - Used for all search operations
  - Consistent threshold and matching strategy applied

---

## 10. MIGRATIONS & DATABASE

### Migration Structure
- [ ] All migrations use transactions where needed
- [ ] Proper rollback implementations in `down()` method
- [ ] Foreign keys with cascading delete/update where appropriate
- [ ] Indexes on frequently queried columns (foreign keys, status, etc.)
- [ ] Column definitions match model attribute types

### Schema Changes
- [ ] ID columns use consistent type (UUID, auto-increment, or custom)
- [ ] Timestamps included where needed (`$table->timestamps()`)
- [ ] String lengths specified where meaningful
- [ ] Nullable columns explicitly marked
- [ ] Enum columns use appropriate database type

---

## 11. TESTING STRATEGY

### Test Structure
- [ ] Feature tests for all endpoints (happy path + error cases)
- [ ] Unit tests for complex business logic
- [ ] Test database properly isolated from production
- [ ] Tests use factories (not hardcoded data)
- [ ] Tests check for proper exception types

### Test Patterns
- [ ] No over-mocking — mock only external dependencies
- [ ] Tests verify behavior, not implementation details
- [ ] No flaky tests (no timing dependencies, consistent data)
- [ ] Error scenarios tested (unauthorized, not found, validation errors)
- [ ] Relationship loading tested (ensure eager loading works)

### Test Coverage Goals
- [ ] All public service methods tested
- [ ] All repository methods with custom queries tested
- [ ] All controllers tested via integration/feature tests
- [ ] All custom validation rules tested
- [ ] All permission checks tested (both allowed and denied paths)

---

## 12. SECURITY AUDIT

### Authentication & Authorization
- [ ] Authentication configured correctly (Sanctum, Passport, etc.)
- [ ] Authorization checked at service layer (not controller)
- [ ] All protected routes use appropriate middleware
- [ ] No authorization bypass vulnerabilities
- [ ] Role/Permission-based access control consistent

### Input Validation & Sanitization
- [ ] All user input validated through Form Requests
- [ ] SQL injection prevention via ORM (never raw queries with user input)
- [ ] CSRF tokens on all POST/PUT/DELETE routes
- [ ] XSS prevention (user data escaped in responses)

### Sensitive Data
- [ ] Passwords hashed (never stored plain text)
- [ ] API tokens masked in logs
- [ ] Sensitive data not exposed in error messages
- [ ] No hardcoded secrets in code (use `.env`)
- [ ] `.env` file not in version control

---

## 13. ARCHITECTURE ALIGNMENT

### Separation of Concerns
- [ ] Controllers handle HTTP only
- [ ] Services handle business logic
- [ ] Repositories handle data access
- [ ] Models define relationships and attributes only
- [ ] Form Requests handle validation
- [ ] Resources handle response transformation

### Dependency Flow
```
Controller → Service → Repository → Model (Eloquent)
                    ↓
                 Helpers (if used)
                    ↓
                Form Request (validation)
                    ↓
                 Resource (response)
```

### No Circular Dependencies
- [ ] Services don't call other services (data isolation)
- [ ] Repositories don't know about services
- [ ] Models don't know about services/repositories
- [ ] Controllers only inject services, not repositories directly

---

## 14. CODE ORGANIZATION

### Directory Structure
```
app/
├── Enums/                    (Application constants)
├── Http/
│   ├── Controllers/          (HTTP handlers)
│   ├── Requests/             (Validation)
│   ├── Resources/            (Response transformation)
│   └── Middleware/
├── Services/                 (Business logic)
│   └── {Feature}/
│       ├── {Name}Service (interface)
│       └── {Name}ServiceImplement
├── Repositories/             (Data access)
│   └── {Feature}/
│       ├── {Name}Repository (interface)
│       └── {Name}RepositoryImplement
├── Helpers/                  (Utilities)
├── Models/                   (Eloquent)
└── Exceptions/
```

- [ ] Code organized by feature/domain
- [ ] Related files located together
- [ ] Naming conventions consistent throughout
- [ ] No files >400 lines (break into smaller units)
- [ ] Namespaces reflect directory structure (PSR-4)

---

## 15. CONSISTENCY PRINCIPLE (CRITICAL)

### Before Making ANY Changes
1. [ ] Check what the codebase already does
2. [ ] If a pattern exists, follow it exactly
3. [ ] Don't introduce multiple ways to do the same thing
4. [ ] Inconsistency is worse than a suboptimal pattern

**This is non-negotiable**: Consistency creates predictability and reduces cognitive load.

---

## Audit Severity Levels

When finding violations, classify as:

- 🔴 **CRITICAL**: Security vulnerabilities, data corruption, production risk
- 🟠 **HIGH**: Architectural violations, performance issues, unmaintainable code
- 🟡 **MEDIUM**: Code smell, technical debt, refactoring candidates
- 🟢 **LOW**: Style improvements, documentation, minor optimizations

---

## Running This Audit

### Quick Check (15 minutes)
```bash
# Format check
vendor/bin/pint --dirty --format agent

# Search for common issues
grep -r "dd(" app/ --include="*.php"
grep -r "dump(" app/ --include="*.php"
grep -r "@todo" app/ --include="*.php"

# Route check
php artisan route:list
```

### Full Audit (1-2 hours)
Go through all 15 sections systematically:
1. Code quality & standards
2. Repository pattern
3. Service layer
4. Controllers
5. Models
6. Enums
7. Form requests
8. API resources
9. Helpers (if used)
10. Migrations & database
11. Testing
12. Security
13. Architecture
14. Code organization
15. Consistency verification

---

## Creating Audit Report

For each finding:

1. **Exact Location**: File, class, method, line number
2. **Severity**: 🔴 CRITICAL / 🟠 HIGH / 🟡 MEDIUM / 🟢 LOW
3. **Rule Violated**: Which section/check failed
4. **Why It Matters**: Impact on security, performance, maintainability
5. **How to Fix**: Structural approach (not code implementation)
6. **Effort**: Small (1-2h) / Medium (2-4h) / Large (4h+)

---

## Usage Notes

### For Code Reviews
Use this checklist during PR reviews. Focus on:
- Permission/authorization validation
- Repository methods returning appropriate types
- Exception handling in services
- Helper usage consistency
- Test coverage

### For Onboarding
New team members should:
1. Understand Repository-Service pattern
2. Review 2-3 existing services/repositories as examples
3. Reference this checklist when implementing features
4. Ask about project-specific conventions not in this generic checklist

### For Continuous Improvement
- Run audit before each release
- Track improvements across audit cycles
- Use failures as teaching moments
- Update team conventions if consensus changes

---

**Status**: Generic Template
**For**: Any Laravel Backend
**Last Updated**: 2026-05-21
