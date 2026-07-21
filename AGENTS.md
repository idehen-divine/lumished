<laravel-boost-guidelines>
=== .ai/backend-development-rules rules ===

# Backend Development Rules

Patterns and rules for this application. Always activate the `backend-development-rules` skill and read the relevant rule file before writing code.

**Skill:** `.agents/skills/backend-development-rules/SKILL.md`

## Consistency First

Check sibling files before applying any rule. If a pattern exists, follow it.

## Rules

| Area | Rule File |
|---|---|
| Repository-Service pattern | `rules/repository-service-pattern.md` |
| Controllers | `rules/controllers.md` |
| Services | `rules/services.md` |
| Repositories | `rules/repositories.md` |
| Models | `rules/models.md` |
| Form Requests | `rules/form-requests.md` |
| API Resources | `rules/resources.md` |
| Enums | `rules/enums.md` |
| Response Codes | `rules/response-code-enum.md` |
| Code Quality & PHPDoc | `rules/code-quality.md` |
| Testing | `rules/testing.md` |
| Helpers | `rules/helpers.md` |

## Non-Negotiables

- PHPDoc on all methods — `@param` and `@return` required; `/** @inheritDoc */` on implementations
- `ResponseCode` enum only — never hardcode HTTP status codes
- `$request->validated()` only — never `$request->all()`
- Run `vendor/bin/sail bin pint --dirty --format agent` after every PHP change
- Write or update tests before finalizing

=== .ai/coding-principles rules ===

# Coding Principles

## 1. Think Before Coding

**Don't assume. Don't hide confusion. Surface tradeoffs.**

Before implementing:
- State your assumptions explicitly. If uncertain, ask.
- If multiple interpretations exist, present them - don't pick silently.
- If a simpler approach exists, say so. Push back when warranted.
- If something is unclear, stop. Name what's confusing. Ask.

## 2. Simplicity First

**Minimum code that solves the problem. Nothing speculative.**

- No features beyond what was asked.
- No abstractions for single-use code.
- No "flexibility" or "configurability" that wasn't requested.
- No error handling for impossible scenarios.
- If you write 200 lines and it could be 50, rewrite it.

Ask yourself: "Would a senior engineer say this is overcomplicated?" If yes, simplify.

## 3. Surgical Changes

**Touch only what you must. Clean up only your own mess.**

When editing existing code:
- Don't "improve" adjacent code, comments, or formatting.
- Don't refactor things that aren't broken.
- Match existing style, even if you'd do it differently.
- If you notice unrelated dead code, mention it - don't delete it.

When your changes create orphans:
- Remove imports/variables/functions that YOUR changes made unused.
- Don't remove pre-existing dead code unless asked.

The test: Every changed line should trace directly to the user's request.

## 4. Goal-Driven Execution

**Define success criteria. Loop until verified.**

Transform tasks into verifiable goals:
- "Add validation" → "Write tests for invalid inputs, then make them pass"
- "Fix the bug" → "Write a test that reproduces it, then make it pass"
- "Refactor X" → "Ensure tests pass before and after"

For multi-step tasks, state a brief plan:
```
1. [Step] → verify: [check]
2. [Step] → verify: [check]
3. [Step] → verify: [check]
```

Strong success criteria let you loop independently. Weak criteria ("make it work") require constant clarification.

## Skills

Do not load any skill by default. Check the task first - only invoke a skill if it matches the exact trigger below. Never
invoke a skill just because it exists.

- `/architect` - before building something non-trivial with no plan yet
- `/audit` - to bootstrap or refresh repo context and MEMORY.md files
- `/check` - when a feature is done and needs a production check or review
- `/debug` - when something is broken and the fix is not obvious
- `/develop` - when implementing an approved feature or slice
- `/document` - for PR, changelog, release note, or postmortem writing
- `/scope` - to turn an idea into a coarse product scope
- `/sync` - as the last step after a change to keep durable knowledge current
- `/test` - to write tests for code you just built or changed

## Durable Knowledge

Project-specific context and conventions are stored in `MEMORY.md`. This file is the durable knowledge base that skills maintain and reference — it persists across rebuilds of the Boost-generated files. All skills should read from and write to `MEMORY.md` for project memory.

=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.3
- laravel/framework (LARAVEL) - v13
- laravel/prompts (PROMPTS) - v0
- laravel/sanctum (SANCTUM) - v4
- laravel/boost (BOOST) - v2
- laravel/mcp (MCP) - v0
- laravel/pail (PAIL) - v1
- laravel/pint (PINT) - v1
- phpunit/phpunit (PHPUNIT) - v12

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Always use `search-docs` before making code changes. Do not skip this step. It returns version-specific docs based on installed packages automatically.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Follow existing application Enum naming conventions.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== phpunit/core rules ===

# PHPUnit

- This application uses PHPUnit for testing. All tests must be written as PHPUnit classes. Use `php artisan make:test --phpunit {name}` to create a new test.
- If you see a test using "Pest", convert it to PHPUnit.
- Every time a test has been updated, run that singular test.
- When the tests relating to your feature are passing, ask the user if they would like to also run the entire test suite to make sure everything is still passing.
- Tests should cover all happy paths, failure paths, and edge cases.
- You must not remove any tests or test files from the tests directory without approval. These are not temporary or helper files; these are core to the application.

## Running Tests

- Run the minimal number of tests, using an appropriate filter, before finalizing.
- To run all tests: `php artisan test --compact`.
- To run all tests in a file: `php artisan test --compact tests/Feature/ExampleTest.php`.
- To filter on a particular test name: `php artisan test --compact --filter=testName` (recommended after making a change to a related file).

=== l0n3ly/laravel-dynamic-helpers rules ===

# Laravel Dynamic Helpers

## Helper Classes

- All helpers live in `app/Helpers/` directory and extend `L0n3ly\LaravelDynamicHelpers\Helper`
- Helper class names use PascalCase with "Helper" suffix: `MoneyHelper`, `PermissionHelper`
- One responsibility per helper — don't mix concerns

## Function Registration

- Functions are automatically registered at service provider boot time with proper type hints
- File paths convert to function names: `Store/CreateHelper.php` → `storeCreateHelper()` function
- All registered functions have return type hints: `function moneyHelper(): \App\Helpers\MoneyHelper`
- Use direct function calls: `moneyHelper()->format()` instead of `helpers()->moneyHelper()->format()`

## Creating Helpers

```bash
php artisan make:helper MoneyHelper
php artisan make:helper Store/CreateHelper
```

No additional commands needed — functions auto-register immediately.

## Public Methods

- All public methods become callable via the function: `public function format()` → `moneyHelper()->format()`
- Private/protected methods are internal use only
- Add type hints to all method parameters and return types

## Dependency Injection

- Use constructor property promotion for dependencies: `public function __construct(protected CurrencyRepository $currencies) {}`
- Container automatically resolves injected dependencies
- Helpers are singletons — instantiated once per request

## Organizing Helpers

- Use subdirectories to organize related helpers: `Admin/`, `Store/`, `Report/`
- Keep directory structure shallow (2-3 levels max)
- Group by feature/domain: `Store/CartHelper`, `Store/CheckoutHelper`, not scattered separately

=== l0n3ly/laravel-repository-with-service rules ===

# Repository + Service Pattern

This package scaffolds the Repository + Service pattern for Laravel with automatic container binding and code
generation. Use repositories for data access and services for business logic orchestration.

## Quick Overview

- **Purpose**: Generate repositories and services, auto-bind implementations, standardize data/business layers.
- **Location**: repositories in `app/Repositories`, services in `app/Services`.
- **Naming**: `Repository` / `Service` interfaces; `RepositoryImplement` / `ServiceImplement` implementations.

## Common Commands

```bash
php artisan make:model User --all
php artisan make:repository Post --service --api
php artisan make:service Order --api
```

## Data Access Methods

Repositories provide these core methods for data access:

- `all()` - Return all records
- `find($id)` - Find by ID
- `findOrFail($id)` - Find or throw exception
- `create($data)` - Create new record
- `update($id, $data)` - Update record (returns Model)
- `delete($id)` - Delete single record
- `destroy(array $ids)` - Delete multiple records
- `query()` - Get fresh query builder
- `updateOrCreate($where, $values)` - Update or create
- `firstOrCreate($where, $values)` - Find or create

## Service Conventions

- Inject repositories via constructor
- Use `ResultService` trait for API responses
- Wrap operations in try-catch
- Keep business logic separate from data access
- Type all parameters and return values

## Documentation

Full documentation available:
- In package: `vendor/l0n3ly/laravel-repository-with-service/docs/`
- Online: https://github.com/l0n3ly/laravel-repository-with-service

</laravel-boost-guidelines>
