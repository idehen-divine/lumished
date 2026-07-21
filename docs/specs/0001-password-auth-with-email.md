# 0001. Password authentication with email

**Date**: 2026-07-21
**Status**: In Progress

## Summary

A complete customer authentication system using email and password with Sanctum API tokens. Users can register, log in, log out, reset their password, verify their email, enable two-factor authentication, update their email, and delete their account. The system follows the Repository-Service pattern already established in this project and uses the Fleet_BE codebase as a reference architecture.

## Context

This application needs a customer facing authentication system. Currently there is a basic User model and Sanctum is installed but no auth endpoints or business logic exist. The route structure already separates admin and customer concerns with prefixes in `bootstrap/app.php`. The fleet management reference project (Fleet_BE) provides a proven pattern for OTP based email verification, password reset, and two-factor authentication flows using the same Laravel, Sanctum, and Repository-Service stack.

The auth system must support GDPR compliance including account deletion and data export. The application runs on SQLite in development, uses Laravel 13, and has the `l0n3ly/laravel-repository-with-service` package installed for the service layer pattern.

## Requirements

**User stories**:
- As a visitor, I want to create an account with my email and password so that I can access the system
- As a registered user, I want to log in and out so that I can use the system securely
- As a user, I want to reset my password if I forget it so that I can regain access
- As a user, I want to verify my email address and update it if needed so that my contact details stay current
- As a user, I want to enable two-factor authentication so that my account is more secure
- As a user, I want to delete my account or export my data so that I can control my personal information

**Acceptance criteria**:
- **AC-1**: A visitor can register with first_name, last_name, email, and a password that is at least 8 characters with at least one uppercase letter and one digit. On success the API returns a Sanctum token and user data. New users start unverified with the CUSTOMER role.
- **AC-2**: A user can log in with email and password. On success the API creates a new Sanctum token, revokes all previous tokens (single device per user), and returns the token. After 5 failed attempts within 1 hour the account is locked. A locked out user receives a lockout error regardless of whether the password is correct.
- **AC-3**: A user can log out. The current Sanctum token is revoked.
- **AC-4**: A user can request a password reset by providing their email. A 6 digit OTP is sent to that email and is valid for 5 minutes. An error is returned if the email is not found but the message does not reveal whether the email exists.
- **AC-5**: A user can verify the password reset OTP by providing the email, OTP code, and the pending token. On success a reset token is returned.
- **AC-6**: A user can reset their password using the reset token and a new password. All existing tokens are revoked. The user must log in again.
- **AC-7**: An authenticated user can update their password by providing their current password, a new password, and an OTP verification flow. Other tokens are revoked but the current session stays active.
- **AC-8**: An authenticated user can verify their email by providing the OTP sent to their email address. On success `email_verified_at` is set.
- **AC-9**: An authenticated user can request a new verification OTP be sent to their email. The previous OTP is invalidated.
- **AC-10**: An authenticated user can update their email. The new email is marked unverified and a verification OTP is sent.
- **AC-11**: An authenticated user can enable 2FA by generating a TOTP secret (returns provisioning URI and QR code), confirming it with a valid TOTP code, and later disabling it by providing their current TOTP code.
- **AC-12**: When a user with 2FA enabled attempts to log in, the API returns a challenge token instead of an auth token. The user must provide the challenge token along with a valid TOTP code (or request an email OTP as fallback) to complete authentication and receive a Sanctum token.
- **AC-13**: An authenticated user can delete their account. The request requires current password confirmation. All user data and tokens are deleted.
- **AC-14**: An authenticated user can export all their personal data as JSON.
- **AC-15**: Protected endpoints (non auth endpoints) require email verification. Unverified users receive a 403 error.
- **AC-16**: Auth endpoints are rate limited. Login and password reset endpoints are throttled.

## Options considered

### Option 1: Custom OTP system (cache based, like Fleet_BE)

A full custom authentication system with cache based 6 digit OTP codes for email verification, password reset, and 2FA. OTPs are stored in cache with a 5 minute TTL. For non production environments the OTP is always 111111 to simplify testing. Uses a dedicated OTP service layer.

**Pros**:
- Unified OTP system across verification, password reset, and 2FA
- Cache based, no extra database tables needed
- Proven pattern from Fleet_BE working in production
- Easy to test with fixed dev OTP

**Cons**:
- More code to write and maintain compared to Laravel built-in notifications
- Cache dependency (Redis must be configured)

### Option 2: Laravel built-in password reset + notifications

Uses Laravel's built-in password reset notifications and the existing `password_reset_tokens` table. Email verification uses Laravel's `MustVerifyEmail` contract. No custom OTP system.

**Pros**:
- Less custom code, uses Laravel conventions
- Well documented, many community examples
- Built-in throttling and token expiration

**Cons**:
- No support for 2FA (must still build custom)
- No unified OTP flow
- Less control over the email verification flow
- Harder to test (tokens are long random strings, not fixed OTPs)

### Option 3: Hybrid (Laravel notifications + custom 2FA only)

Uses Laravel's built-in password reset for forgot password, `MustVerifyEmail` for email verification, and builds custom 2FA logic only.

**Pros**:
- Less custom code for the basics
- Focuses custom effort on 2FA only

**Cons**:
- Two different patterns (notifications vs OTP)
- More cognitive overhead for developers
- No unified OTP service to reuse

## Decision

**Chosen option**: Option 1: Custom OTP system (cache based, like Fleet_BE)

The custom OTP system provides a single, consistent pattern across all flows. The Fleet_BE reference proves this approach works reliably in production with the same stack. The cost is more initial code, but the unified pattern reduces long-term maintenance complexity. A single OTP service handles email verification, password reset, and 2FA email fallback, each with the same 6 digit, 5 minute TTL, same notification Mailable pattern, and same test approach.

**Implementation skills**: `backend-development-rules` (`l0n3ly/laravel-boost`, `.agents/skills/backend-development-rules/`) -- defines the Repository-Service pattern, controllers, form requests, resources, enums, testing, and code quality standards that this feature must follow

## Rationale

The stack already matches Fleet_BE (Laravel 13, Sanctum, Repository-Service pattern, Dynamic Helpers). Using a custom OTP system rather than Laravel's built-in notifications keeps every auth flow on a single consistent pattern. Developers only need to understand one OTP mechanism, one email sending pattern, and one test approach. The `111111` dev OTP convention makes tests reliable and fast.

The cache based approach avoids adding new database tables. Since the project already uses Redis for the queue, the cache dependency is already met. The 5 minute TTL matches the Fleet_BE convention and is suitable for all current flows.

## Feature design

**Data model sketch**:

`users` table (updated migration):
- `id` uuid (primary key, uses `HasUuids`)
- `first_name` string, required
- `last_name` string, nullable
- `other_name` string, nullable
- `email` string, required, unique
- `password` string, required (hashed cast)
- `phone_no` string, nullable
- `profile_image` string, nullable
- `gender` string, nullable (enum: MALE, FEMALE)
- `status` string, default ACTIVE (enum: ACTIVE, SUSPENDED)
- `email_verified_at` timestamp, nullable
- `two_factor_secret` text, nullable (encrypted cast)
- `two_factor_confirmed_at` timestamp, nullable
- `remember_token` string, nullable
- timestamps

`personal_access_tokens` table (already exists from Sanctum migration).

`password_reset_tokens` table (kept as is from Laravel default, may not be used directly).

**New entities**:
- `RoleEnum` already exists with ADMIN and CUSTOMER cases (no changes needed)
- `ResponseCode` enum needs to be created

**API surface**:

| Endpoint | Method | Key inputs | Key outputs | Auth | Key errors |
|---|---|---|---|---|---|
| /api/v1/customers | POST | first_name:str(req), last_name:str(req), email:email(req), password:str(req), password_confirmation:str(req) | user, token | guest | 422 validation |
| /api/v1/sessions | POST | email:email(req), password:str(req) | user, token | guest | 401 invalid, 423 locked, 428 2FA challenge |
| /api/v1/sessions/current | DELETE | none | message | bearer | 401 |
| /api/v1/sessions/two-factor | POST | challenge_token:str(req), method:str(req), code:str(req) | user, token | guest | 401 invalid |
| /api/v1/password-resets | POST | email:email(req) | pending_token, message | guest | 422, 429 |
| /api/v1/password-resets/{token} | PUT | reset_token:str(req), password:str(req), password_confirmation:str(req) | message | guest | 422, 410 expired |
| /api/v1/customers/me/password | PATCH | current_password:str(req), new_password:str(req), new_password_confirmation:str(req) | message | bearer | 422, 401 |
| /api/v1/email-verifications | POST | none (empty body) | message | bearer | 403 verified, 429 |
| /api/v1/email-verifications/verify | POST | otp:str(req, size:6) | message | bearer | 422, 410 expired |
| /api/v1/customers/me/email | PATCH | email:email(req) | message | bearer | 422, 409 duplicate |
| /api/v1/two-factor/setup | POST | none | secret, qr_code_uri | bearer | 409 already enabled |
| /api/v1/two-factor/confirm | POST | code:str(req, digits:6) | message | bearer | 422 invalid code |
| /api/v1/two-factor | DELETE | code:str(req, digits:6) | message | bearer | 422 |
| /api/v1/customers/me | DELETE | password:str(req) | message | bearer | 401, 422 |
| /api/v1/customers/me/export | GET | none | user data JSON | bearer | 401 |

**Value sourcing**:

| Action | Value produced or displayed | Source |
|---|---|---|
| Register | auth token | Sanctum `createToken` |
| Register | user data | `UserResource` from DB |
| Register | CUSTOMER role | `RoleEnum::CUSTOMER` |
| Login | auth token (or challenge token) | Sanctum `createToken` or cache generated challenge |
| Login | single device enforcement | `$user->tokens()->delete()` before creating new token |
| Login | lockout check | cache key `login_lockout:{email}` |
| Password reset | pending token | random string, cached with `password_reset_pending:{hash}` |
| Password reset | reset token | random string, cached with `password_reset:{hash}` |
| Password reset | OTP | `OTPService`, cache key `password_reset_{email}` |
| Email verification | OTP | `OTPService`, cache key `email_verification_{email}` |
| 2FA setup | TOTP secret | `Laravel\Fortify` or `pragmarx/google2fa-laravel` generated |
| 2FA confirm | `two_factor_confirmed_at` | DB column, set on valid TOTP code |
| 2FA login | challenge token | random UUID, cached with `2fa_challenge:{hash}` |
| Account deletion | all user data removed | DB delete cascading |
| Data export | full user profile JSON | DB query, all related data |

**Key invariants**:
- Email must be unique per user (database unique index)
- Password must always be hashed (cast `password => hashed`)
- One active token per user at any time (enforced in service on login)
- `two_factor_secret` must be encrypted at rest (cast `encrypted`)
- OTP expires after 5 minutes (cache TTL)
- Login lockout lasts 1 hour (cache TTL)
- A user without `email_verified_at` cannot access endpoints that require verification

**Security model**:

| Endpoint | Who can access | Notes |
|---|---|---|
| POST /api/v1/customers | Everyone (guest) | No auth required |
| POST /api/v1/sessions | Everyone (guest) | Rate limited |
| POST /api/v1/sessions/two-factor | Everyone (guest) | Requires challenge token |
| DELETE /api/v1/sessions/current | Authenticated user only | Bearer token |
| POST /api/v1/password-resets | Everyone (guest) | Rate limited |
| PUT /api/v1/password-resets/{token} | Everyone (guest) | Requires reset token |
| PATCH /api/v1/customers/me/password | Authenticated user only | Own account |
| POST /api/v1/email-verifications | Authenticated, unverified | Own account |
| POST /api/v1/email-verifications/verify | Authenticated | Own account |
| PATCH /api/v1/customers/me/email | Authenticated | Own account |
| POST /api/v1/two-factor/setup | Authenticated | Own account |
| POST /api/v1/two-factor/confirm | Authenticated | Own account |
| DELETE /api/v1/two-factor | Authenticated | Own account |
| DELETE /api/v1/customers/me | Authenticated | Own account, password confirmation |
| GET /api/v1/customers/me/export | Authenticated | Own account |

All endpoints require email verification before access (via `verified` middleware) except: register, login, 2FA login, password reset flow.

**Configuration required**:
- `REDIS_HOST`, `REDIS_PASSWORD`, `REDIS_PORT`: Redis connection for queue and OTP cache
- `QUEUE_CONNECTION=sync` for dev, `QUEUE_CONNECTION=redis` for production
- `APP_URL`: used for email links and Sanctum stateful domains
- `SANCTUM_STATEFUL_DOMAINS`: for SPA cookie based auth if needed later

**Critical test scenarios**:
- Happy path register: POST /api/v1/customers with valid data returns 201 with token and user, verifies AC-1
- Happy path login: POST /api/v1/sessions with valid credentials returns 200 with token, old tokens revoked, verifies AC-2
- Login failure: wrong password returns 401, verifies AC-2
- Login lockout: 5 failed attempts returns 423, verifies AC-2
- Logout: DELETE /api/v1/sessions/current revokes token, verifies AC-3
- Password reset flow: request OTP -> verify OTP -> reset password -> login with new password, verifies AC-4, AC-5, AC-6
- Update password: authenticated PATCH with valid current password, verifies AC-7
- Email verification: POST verify with valid OTP sets email_verified_at, verifies AC-8
- 2FA enable: setup returns secret -> confirm with TOTP code succeeds, verifies AC-11
- 2FA login: POST /api/v1/sessions returns challenge token -> POST /api/v1/sessions/two-factor with TOTP code returns auth token, verifies AC-12
- Unverified user: accessing protected endpoint returns 403, verifies AC-15

## Build plan

Build approach: Tracer Bullet (end-to-end thin vertical slices through every layer). Each task delivers a working slice from migration through test.

1. Create ResponseCode enum and update users migration (UUID primary key, add first_name, last_name, other_name, phone_no, profile_image, gender, status fields), satisfies AC-1
2. Create User model with HasUuids, casts, accessor for full_name, relationship stubs (reference Fleet_BE), satisfies AC-1
3. Create UserRepository (interface + implementation) with findByEmail, createCustomer, findById methods, satisfies AC-1
4. Create UserResource for API responses, satisfies AC-1
5. Create RegisterRequest, LoginRequest form requests with validation rules (password min 8, uppercase + digit), satisfies AC-1, AC-2
6. Create AuthService (interface + implementation) with register, login, logout methods including single device token revocation, satisfies AC-1, AC-2, AC-3
7. Create AuthController with register, login, logout methods, wire routes (customer group, /api/v1 prefix), satisfies AC-1, AC-2, AC-3
8. Create tests for register, login (including lockout), logout flows, satisfies AC-1, AC-2, AC-3, AC-16
9. Create OTP service (interface + implementation) with createAndSendOTP, verifyOtpCode methods, cache based, 6 digits, 5 min TTL, dev OTP 111111, satisfies AC-4, AC-5, AC-8, AC-9
10. Create Mailables for password reset OTP, email verification OTP, 2FA email fallback, welcome email, satisfies AC-4, AC-8, AC-12
11. Create password reset flow (ForgotPasswordRequest, VerifyOtpRequest, ResetPasswordRequest + service methods + controller + routes + tests), satisfies AC-4, AC-5, AC-6
12. Create email verification flow (VerifyEmailRequest + service methods + controller + routes + tests), satisfies AC-8, AC-9, AC-10, AC-15
13. Create update password flow (UpdatePasswordRequest + service method + controller + route + tests), satisfies AC-7
14. Create update email flow (UpdateEmailRequest + service method + controller + route + tests), satisfies AC-10
15. Create 2FA flow (setup + confirm + disable + 2FA challenge login, four endpoints + tests), satisfies AC-11, AC-12
16. Create GDPR endpoints (account deletion with password confirm + data export, tests), satisfies AC-13, AC-14
17. Add rate limiting to auth endpoints and lockout logic to login service, satisfies AC-16
18. Run `vendor/bin/pint --format agent` on all new files

## Consequences

**Positive**:
- Single, consistent OTP pattern across all auth flows
- UUID primary keys from the start, no migration pain later
- Testable with fixed dev OTP (111111)
- Full GDPR compliance with deletion and export
- Two-factor authentication increases account security
- Proved pattern from Fleet_BE running in production

**Negative or tradeoffs**:
- More initial code than using Laravel's built-in auth notifications
- Redis must be running for OTP cache and email queue
- Single device per user policy may frustrate users with multiple devices
- Custom OTP logic is additional surface area for bugs compared to Laravel defaults

**Neutral**:
- Users migration will be modified (needs fresh migration or a new one for existing installs)
- Existing `password_reset_tokens` table remains but may not be used if custom OTP covers password reset
- Route structure changes from current empty files to full auth routing

## Follow-up

- [ ] Create the `ResponseCode` enum in `app/Enums/ResponseCode.php` with the 8 standard cases as a backed integer enum (it is referenced by the existing `LogAndRespond` trait but does not exist yet)
