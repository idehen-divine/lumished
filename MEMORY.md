# MEMORY.md

Durable project context and knowledge base. Managed by AI skills; persists across Boost regenerations.

## Stack

- **Language / Runtime**: PHP 8.3
- **Framework**: Laravel 13
- **Key dependencies**: Laravel Boost, Laravel Sanctum, Laravel Pint
- **Package manager**: Composer

## Commands

```bash
# Development
composer run dev

# Format
vendor/bin/pint --dirty --format agent

# Test
php artisan test --compact
```

## Rules

- PHPDoc on all methods — `@param` and `@return` required
- `ResponseCode` enum only — never hardcode HTTP status codes
- `$request->validated()` only — never `$request->all()`
- Run `vendor/bin/pint --dirty --format agent` after every PHP change
- Write or update tests before finalizing

## Active skills

<!-- Populated by /audit and /sync as skills are installed and used -->

## Context files

<!-- Nested MEMORY.md files are listed here as they are created -->
