# MEMORY.md

Durable project context and knowledge base. Managed by AI skills; persists across Boost regenerations.

## Stack

- **Language / Runtime**: PHP 8.3
- **Framework**: Laravel 13
- **Key dependencies**: Laravel Boost, Laravel Sanctum, Laravel Pint
- **Package manager**: Composer
- **Database**: SQLite (development), Redis (cache, queue)
- **Key packages**: spatie/laravel-permission, kreait/firebase-php, pragmarx/google2fa-laravel, yabacon/paystack-php, l0n3ly/laravel-repository-with-service, l0n3ly/laravel-dynamic-helpers

## Build approach

Tracer Bullet - end to end thin vertical slices through every layer (from the auth spec).

## Commands

```bash
# Full project setup
composer run setup

# Development
composer run dev

# Format
vendor/bin/pint --dirty --format agent

# Test
php artisan test --compact
```

## Specs

Stored in `docs/specs/`. Format: `docs/specs/NNNN-title.md`.

## Rules

- PHPDoc on all methods — `@param` and `@return` required
- `ResponseCode` enum only — never hardcode HTTP status codes
- `$request->validated()` only — never `$request->all()`
- Run `vendor/bin/pint --dirty --format agent` after every PHP change
- Write or update tests before finalizing

## Agent skills

Installed in `.agents/skills/`. Load only what a task needs.

- [architect](.agents/skills/architect/): design decisions and build specs
- [audit](.agents/skills/audit/): context bootstrapper, MEMORY.md writer
- [backend-development-rules](.agents/skills/backend-development-rules/): repository-service pattern, controllers, models, testing
- [check](.agents/skills/check/): production check and code review before merge
- [debug](.agents/skills/debug/): root cause bug finding and fixing
- [develop](.agents/skills/develop/): feature building from approved specs
- [document](.agents/skills/document/): PR, changelog, release notes, postmortem
- [helper-creation](.agents/skills/helper-creation/): Laravel Dynamic Helpers creation
- [laravel-best-practices](.agents/skills/laravel-best-practices/): Laravel code quality and patterns
- [laravel-permission-development](.agents/skills/laravel-permission-development/): Spatie roles, permissions, policies
- [repository-generator](.agents/skills/repository-generator/): generate repository classes
- [scope](.agents/skills/scope/): product scoping to docs/scope/
- [service-binding](.agents/skills/service-binding/): DI, container binding for repos and services
- [service-generator](.agents/skills/service-generator/): generate service classes
- [sync](.agents/skills/sync/): durable knowledge sync after changes
- [test](.agents/skills/test/): test writing for built or changed code

## Context files

<!-- Nested MEMORY.md files are listed here as they are created -->
