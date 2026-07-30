# 0003. Admin management API

**Date**: 2026-07-22
**Status**: In Progress

## Summary

The admin management API gives admins and the platform owner oversight and control over the platform. Admins can manage customers (list, view, update, control status, delete if granted permission), oversee stores and products (endpoints defined in spec 0002), view a dashboard with platform counts, configure system settings, and view the activity log. The platform owner (OWNER role) can additionally create and delete admin accounts. All endpoints share the `/api/v1/admin/` prefix with Sanctum auth, admin role, email verification, and per-action Spatie permission checks.

## Context

The platform has three roles: CUSTOMER, ADMIN, and OWNER. Admin auth (login, logout, password reset, 2FA, account management) was built in spec 0001. Store and product management (spec 0002) sketched four admin oversight endpoints (list stores, view store with products, manage store status, list products in a store). What is missing is the full admin backend: a way to manage customer accounts, manage admin users (OWNER only), view platform wide dashboard data, configure system settings, and see an audit log of admin actions.

Without this, admins have no way to respond to customer issues (suspend accounts, edit details), the platform owner cannot manage the admin team, and there is no visibility into what admins are doing. The activity log is especially important for compliance and accountability.

The store oversight endpoints from spec 0002 are included here by reference. The new build work covers customer management, admin user management (OWNER), dashboard, settings, and activity log.

## Requirements

**User stories**:
- As an admin, I want to view all stores and manage their status so that I can keep the platform orderly
- As an admin, I want to view products in any store so that I can review listings
- As an admin, I want to list, view, update, and manage customer accounts so that I can help users
- As an admin, I want to see a dashboard with platform counts so that I can understand platform health at a glance
- As an admin, I want to configure system settings so that I can update platform name, support email, and other values
- As an admin, I want to view the activity log so that I can see what actions were taken and by whom
- As the platform owner, I want to create and delete admin accounts so that I can manage the admin team

**Acceptance criteria** (the contract, each criterion is independently checkable):
- **AC-1**: Admin can list all stores (paginated, with optional status filter) using the store oversight endpoints from spec 0002
- **AC-2**: Admin can view a single store with its products using the store oversight endpoints from spec 0002
- **AC-3**: Admin can update a store's status (active or suspended) using the store oversight endpoints from spec 0002
- **AC-4**: Admin can list products in a specific store (paginated) using the store oversight endpoints from spec 0002
- **AC-5**: Admin can list all customers (paginated, with optional status filter). Results include the customer's id, first_name, last_name, email, status, store_count, and created_at. All list endpoints use page-based pagination with a default of 15 items per page and a maximum of 100.
- **AC-6**: Admin can view a single customer's details including their profile fields and a list of their stores
- **AC-7**: Admin can update a customer's profile fields (first_name, last_name, email, phone, other_name)
- **AC-8**: Admin can update a customer's status between active and suspended. Suspending a customer also suspends all their stores. Reactivating the customer does NOT automatically reactivate their stores (an admin must reactivate each store manually).
- **AC-9**: Admin can delete a customer only if they have the `can_delete_customers` permission. The delete is hard (no soft delete). All the customer's stores, products, categories, and pivot rows are also deleted.
- **AC-10**: OWNER can list all admin users (paginated). Each entry shows id, first_name, last_name, email, and created_at.
- **AC-11**: OWNER can create a new admin user by providing first_name, last_name, and email. A random password is generated and the new admin must use the password reset flow to set their own password.
- **AC-12**: OWNER can update an admin user's first_name, last_name, and email.
- **AC-13**: OWNER can delete an admin user. The admin's Sanctum tokens are revoked. There is no guard against deleting the last admin.
- **AC-14**: GET `/admin/dashboard` returns total_stores, total_customers, and total_admins as integer counts. Only active stores count toward total_stores. Only non admin users count toward total_customers. Only active admins count toward total_admins. Customer and admin counts use Spatie's role scoping, not a database role column.
- **AC-15**: Admin can list all system settings as key value pairs. Keys are defined by the `SettingKey` backend enum. Initial keys are platform_name, support_email, maintenance_mode, and default_currency.
- **AC-16**: Admin can update system settings via a batch PUT endpoint. The request body is a JSON object of `{ "key": "value" }` pairs. Only keys that exist in the `SettingKey` enum are accepted. Unknown keys are rejected with a 422 error.
- **AC-17**: Admin can view the activity log (paginated, default 15 per page, max 100). Supports filtering by resource_type (exact match), actor_id (UUID), date_from, and date_to. Results are in reverse chronological order. The response exposes id, user (id, name, email), action, resource_type, resource_id, details, ip_address, user_agent, and created_at.
- **AC-18**: Every admin write action (create, update, delete, status change on any resource, plus settings updates) is automatically recorded in the activity log. Read actions (GET) are not logged. The log entry captures who did it, what action, what resource, the resource ID, any additional details as JSON, the IP address, and the user agent. Logging is synchronous but outside the main action's database transaction.
- **AC-19**: All admin management endpoints require `auth:sanctum`, admin role (via Spatie middleware), and email verification. OWNER endpoints additionally require the `manage_admins` permission.
- **AC-20**: Admin endpoints are rate limited to 30 requests per minute using the admin:api throttle key (separate from the customer throttle:api key). Dashboard and settings endpoints are included in this limit.
- **AC-21**: Suspending a customer cascades to suspend all their stores (store status changes to suspended). This is done in the service layer within a single transaction.

## Options considered

### Option 1: Single controller per domain with shared service layer

Each admin domain (customers, settings, activity log, dashboard) gets its own controller. Store oversight controllers are already designed in spec 0002. OwnER endpoints for admin management use the same controller but are gated by a separate middleware check.

**Pros**:
- Clear separation of concerns. Each controller has one job.
- Follows the existing pattern in the codebase (CustomerAuthController, AdminAuthController).
- Easy to test each domain independently.

**Cons**:
- More files, but that is acceptable for clear separation.

### Option 2: Single monolithic AdminController

One controller handles all admin endpoints. Methods are grouped by domain.

**Pros**:
- Fewer files.
- One place to add admin middleware.

**Cons**:
- Violates the single responsibility principle. A controller with 15+ methods is hard to read and test.
- Does not match the existing codebase pattern.

### Option 3: Activity log via middleware vs. service layer hook

Record admin actions automatically in middleware that wraps every admin write endpoint, versus recording them in each service method.

**Pros of service layer**:
- Explicit and visible in the code. A developer can see exactly when and what is logged.
- Can access domain specific data (like the resource being modified) that middleware cannot easily reach.
- Follows the existing pattern of putting cross cutting concerns in the service layer.

**Cons of middleware**:
- Middleware runs before the controller, so it does not have access to the response or the affected resource ID.
- Would need to log after the action completes, which means an after middleware or a terminate callback, making the pattern harder to follow.

## Decision

**Chosen option**: Option 1 (single controller per domain with shared service layer) combined with service layer activity logging (not middleware).

Each admin domain gets its own controller. The store oversight controllers (AdminStoreController) are defined in spec 0002 and are not rebuilt here. New controllers: AdminCustomerController, AdminUserController (OWNER admin management), DashboardController, SettingController, and ActivityLogController.

Activity logging is done at the service layer. Each service method that performs a write action calls a dedicated `ActivityLogService` method after the write succeeds. This keeps the logging explicit and testable without hidden magic.

The OWNER check for admin management endpoints uses a Spatie permission check (`manage_admins` permission) assigned only to the OWNER role. The `can_delete_customers` permission is assignable to individual admins by the OWNER.

**Implementation skills**: `backend-development-rules` (`.agents/skills/backend-development-rules/`) defines the repository service pattern, controllers, form requests, resources, and code quality standards this feature must follow. `laravel-permission-development` (`.agents/skills/laravel-permission-development/`) defines the permission seeding and role assignment pattern.

## Rationale

A controller per domain matches the existing codebase pattern (separate controllers for different concerns), keeps each class short, and makes testing easier. The store oversight controllers are already designed in spec 0002 and should not be duplicated; this spec references them and adds the remaining admin domains.

Service layer activity logging is more explicit than middleware. The service method has the full context of the action (the affected model, the specific changes) and can pass meaningful detail to the log entry. A middleware approach would need an after hook and would struggle to capture the resource ID without extra code in every controller.

The OWNER role check via a dedicated permission (`manage_admins`) keeps the permission system consistent with the existing Spatie setup. The `can_delete_customers` permission is a separate permission so the OWNER can grant it selectively without giving full admin powers.

## Feature design

**Data model sketch**:

**ActivityLog**
- id: uuid (primary key)
- user_id: uuid (FK to users), nullable (keeps the log if the user is later deleted)
- action: string (255), required (values: created, updated, deleted, suspended, activated)
- resource_type: string (255), required (values: store, product, category, user, setting)
- resource_id: string (255), nullable (UUID of the affected resource)
- details: json, nullable (extra context like field changes, previous values)
- ip_address: string (45), nullable
- user_agent: text, nullable
- created_at: timestamp (not updated on modification)
- Index on (resource_type, resource_id)
- Index on user_id
- Index on created_at

**SystemSetting**
- id: uuid (primary key)
- key: string (255), required, unique
- value: text, nullable
- description: string (255), nullable
- timestamps

**SettingKey enum** (backed string enum, defines valid keys):
- PlatformName = 'platform_name'
- SupportEmail = 'support_email'
- MaintenanceMode = 'maintenance_mode'
- DefaultCurrency = 'default_currency'

**State transitions**:
- User: active <-> suspended (admin action). Suspending a user also suspends all their stores.
- Store: active <-> suspended (admin action). Already defined in spec 0002.

**API surface**:

All endpoints are under the `/api/v1/admin/` prefix. The auth column shows the middleware checks applied. OWNER endpoints additionally require the `manage_admins` permission.

**Store oversight (from spec 0002, included here by reference)**:
| Endpoint | Method | Key inputs | Key outputs | Auth | Key errors |
|---|---|---|---|---|---|
| /admin/stores | GET | page, per_page, status(opt) | paginated store list | auth:sanctum + admin + verified | 403, 422 |
| /admin/stores/{id} | GET | id:uuid(path) | store with products | auth:sanctum + admin + verified | 403, 404 |
| /admin/stores/{id}/status | PUT | status:str(req, active/suspended) | updated store | auth:sanctum + admin + verified | 403, 422 |
| /admin/store/{storeSlug}/products | GET | storeSlug:str(path), page, per_page | paginated products | auth:sanctum + admin + verified | 403, 404 |

**Customer management**:
| Endpoint | Method | Key inputs | Key outputs | Auth | Key errors |
|---|---|---|---|---|---|
| /admin/customers | GET | page, per_page, status(opt) | paginated customer list | auth:sanctum + admin + verified | 422 |
| /admin/customers/{id} | GET | id:uuid(path) | customer with stores list | auth:sanctum + admin + verified | 403, 404 |
| /admin/customers/{id} | PUT | first_name(opt), last_name(opt), email(opt), phone(opt), other_name(opt) | updated customer | auth:sanctum + admin + verified | 403, 422 |
| /admin/customers/{id}/status | PUT | status:str(req, active/suspended) | updated customer | auth:sanctum + admin + verified | 403, 422 |
| /admin/customers/{id} | DELETE | id:uuid(path) | message | auth:sanctum + admin + verified + permission:can_delete_customers | 403, 404 |

**Admin user management (OWNER only)**:
| Endpoint | Method | Key inputs | Key outputs | Auth | Key errors |
|---|---|---|---|---|---|
| /admin/admins | GET | page, per_page | paginated admin list | auth:sanctum + admin + verified + permission:manage_admins | 403 |
| /admin/admins | POST | first_name:str(req), last_name:str(req), email:email(req) | created admin user | auth:sanctum + admin + verified + permission:manage_admins | 403, 422 |
| /admin/admins/{id} | PUT | first_name(opt), last_name(opt), email(opt) | updated admin user | auth:sanctum + admin + verified + permission:manage_admins | 403, 404, 422 |
| /admin/admins/{id} | DELETE | id:uuid(path) | message | auth:sanctum + admin + verified + permission:manage_admins | 403, 404 |

**Dashboard**:
| Endpoint | Method | Key inputs | Key outputs | Auth | Key errors |
|---|---|---|---|---|---|
| /admin/dashboard | GET | none | total_stores:int, total_customers:int, total_admins:int | auth:sanctum + admin + verified | 403 |

**System settings**:
| Endpoint | Method | Key inputs | Key outputs | Auth | Key errors |
|---|---|---|---|---|---|
| /admin/settings | GET | none | { key: value, ... } object | auth:sanctum + admin + verified | 403 |
| /admin/settings | PUT | { key: value, ... } object | updated settings object | auth:sanctum + admin + verified | 403, 422 |

**Activity log**:
| Endpoint | Method | Key inputs | Key outputs | Auth | Key errors |
|---|---|---|---|---|---|
| /admin/activity-logs | GET | page, per_page, resource_type(opt), actor_id(opt), date_from(opt), date_to(opt) | paginated log entries | auth:sanctum + admin + verified | 422 |

**Value sourcing** (every value each action produces, computes, or displays names where it comes from):
| Action | Value produced / displayed | Source |
|---|---|---|
| List customers | paginated customer list | DB query using Spatie `role:customers` scope, optional status filter |
| List customers | store_count per customer | `withCount('stores')` on the User model, computed at query time |
| View customer | customer profile fields + store list | DB query on users table + eager loaded stores |
| Update customer | updated customer fields | input params, validated by UpdateCustomerRequest |
| Update customer status | status changed | input param (active/suspended), validated by UpdateCustomerStatusRequest |
| Customer delete | cascade delete | DB, deletes customer + stores + products + categories + pivot rows |
| Suspend customer | store suspension | service layer, updates store status to suspended after user status update, within a transaction |
| List admins | paginated admin list | DB query using Spatie `role:admin` scope |
| Create admin | new admin user | input params, random password generated via Str::random(16), password reset email sent to the new admin's email using the existing OTP service |
| Update admin | updated admin user | input params, validated by UpdateAdminRequest |
| Delete admin | removed admin | DB delete, tokens revoked via `$admin->tokens()->delete()` |
| Dashboard total_stores | store count | DB count on stores where status = active |
| Dashboard total_customers | customer count | DB count using Spatie role scope for users with CUSTOMER role and status = active |
| Dashboard total_admins | admin count | DB count using Spatie role scope for users with ADMIN role and status = active |
| List settings | key value pairs | DB query on system_settings table, returned as a flat { key: value } object |
| Update settings | updated values | input object, key validated against SettingKey enum, value upserted |
| List activity log | paginated log entries | DB query on activity_logs table, filters applied, ordered by created_at desc |
| Log admin action | activity log entry | service layer, called after each write action, captures user_id, action, resource_type/id, details, ip_address, user_agent |
| Log entry action value | created/updated/deleted/suspended/activated | hardcoded in the service method call based on the operation |
| Log entry resource_type | store/product/category/user/setting | determined by the model class in the service method call |
| Log entry ip_address | request IP | `request()->ip()` at the time of the action |
| Log entry user_agent | request user agent | `request()->userAgent()` at the time of the action |

**Key invariants**:
- Every admin write action is logged. Logging is synchronous but outside the main action's database transaction. If the log write fails it is caught and logged to the error log, but the main action is not rolled back.
- Customer status and store status are separate fields. Suspending a customer sets both the customer status and their stores' status to suspended. Reactivating the customer does not reactivate stores.
- A customer with suspended stores cannot be reactivated to active if any owned store is still suspended (the admin must reactivate stores first, or the system checks this). Actually, the user status is independent: a suspended user can be reactivated even if their stores are still suspended. The stores remain hidden because their status is suspended.
- System setting keys are defined by the SettingKey enum. No new key can be added without updating the enum.
- Deleting a customer is a hard delete. All their stores, products, categories, and pivot rows are deleted. This is not reversible.
- Deleting an admin revokes their Sanctum tokens. Other admins' sessions are unaffected.
- Activity log entries are append only. No update or delete of log entries.
- Role queries (listing customers vs admins) use Spatie's role scoping (e.g. `role:customers` scope), not a `role` column on the users table.
- The `can_delete_customers` permission is not assigned to the ADMIN role by default. The OWNER must grant it per admin.
- The `manage_admins` permission is assigned only to the OWNER role, not to ADMIN.

**New Spatie permissions to seed**:
Permissions added to the seeding (RolePermissionSeeder from spec 0001):
- ADMIN role gets: view_customers, manage_customers, manage_settings, view_activity_logs, view_dashboard (in addition to view_stores, manage_stores, view_products from spec 0002)
- OWNER role gets all ADMIN permissions plus manage_admins and can_delete_customers
- can_delete_customers is also assignable to individual admins by the OWNER

**Security model**:
| Endpoint group | Who can access | Permission check | Notes |
|---|---|---|---|
| Store oversight | ADMIN role | view_stores (GET), manage_stores (PUT) | Endpoints from spec 0002 |
| Customer list/view | ADMIN role | view_customers | Read only access to customer data |
| Customer update/status | ADMIN role | manage_customers | Write access to customer profiles and status |
| Customer delete | ADMIN role | must_have can_delete_customers | Guarded by a separate permission |
| Admin management | OWNER role | manage_admins | Only OWNER level can manage admins |
| Dashboard | ADMIN role | view_dashboard | Simple read only endpoint |
| Settings | ADMIN role | manage_settings | Read and write |
| Activity log | ADMIN role | view_activity_logs | Read only, filtered |
| All endpoints | any | email_verified_at must be set | All management routes require verified middleware |

**Configuration required**:
No new environment variables. The existing auth rate limiting and Redis cache configuration are sufficient.

**Critical test scenarios** (each maps to an acceptance criterion in Requirements):
- Happy path customer management: admin lists customers, views one, updates their name, suspends them, verifies stores are also suspended, verifies **AC-5, AC-6, AC-7, AC-8, AC-21**
- Customer delete with permission: admin with can_delete_customers permission deletes a customer, verifies cascade delete, verifies **AC-9**
- Customer delete without permission: admin without can_delete_customers receives 403, verifies **AC-9**
- OWNER admin management: owner lists admins, creates a new admin (password reset email sent), updates an admin's name, deletes an admin, verifies **AC-10, AC-11, AC-12, AC-13**
- OWNER permission check: admin (non owner) attempts to access admin management endpoints, receives 403, verifies **AC-19**
- Dashboard: GET /admin/dashboard returns correct integer counts, verifies **AC-14**
- Settings: admin lists settings, updates a setting, verifies updated value, verifies **AC-15, AC-16**
- Settings invalid key: PUT with unknown key returns 422, verifies **AC-16**
- Activity log: perform an admin write action, verify the log entry appears in the activity log list, verifies **AC-17, AC-18**
- Activity log filters: filter by resource_type and actor_id returns matching entries only, verifies **AC-17**
- Rate limiting: hitting an admin endpoint 31 times in quick succession returns 429, verifies **AC-20**
- Customer reactivation: suspend a customer, reactivate them, verify stores remain suspended, verifies **AC-8**

## Build plan

Build approach: Tracer Bullet (end to end thin vertical slices through every layer). Each task delivers a working slice from migration through test.

1. Create the ActivityLog and SystemSetting migrations, plus the SettingKey backed string enum, satisfies **AC-15, AC-18**
2. Create ActivityLog model with HasUuids, casts (details: json), relationships, and SystemSetting model with HasUuids, satisfies **AC-15, AC-18**
3. Create ActivityLogRepository with interface and implementation following the existing pattern, satisfies **AC-5, AC-15**. SystemSetting uses a helper pattern (not repository-service stack) since it is a simple key value store with four known keys.
4. Create form requests (ListCustomersRequest, UpdateCustomerRequest, UpdateCustomerStatusRequest, CreateAdminRequest, UpdateAdminRequest, UpdateSettingsRequest, ListActivityLogsRequest), satisfies **AC-5 through AC-17**
5. Create API resources (AdminCustomerResource, AdminUserResource, ActivityLogResource) matching the existing UserResource pattern, satisfies **AC-5, AC-10, AC-17**. SystemSetting does not need a dedicated resource (returns a flat JSON object).
6. Create ActivityLogService with a `log(string $action, string $resourceType, ?Model $resource = null, ?array $details = null): void` method that records the action with the authenticated user's ID, IP, and user agent. Logging is synchronous but outside the main action's database transaction. If the log write fails, the main action still succeeds, satisfies **AC-18**
7. Create AdminCustomerService with methods for list, view, update, status change (with store suspension cascade), and delete (with permission check). Role queries use Spatie's role scoping, not a role column, satisfies **AC-5 through AC-9, AC-21**
8. Create AdminUserService (OWNER) with methods for list, create (with random password generation and password reset email sent to the new admin, using the existing OTP service), update (name and email), and delete (with token revocation), satisfies **AC-10, AC-11, AC-12, AC-13**
9. Create DashboardService with a method that returns total_stores, total_customers, total_admins counts using Spatie's role scoping, satisfies **AC-14**
10. Create a SettingService or SettingHelper with list and batch update methods, validates keys against SettingKey enum, satisfies **AC-15, AC-16**
11. Create controllers (AdminCustomerController, AdminUserController, DashboardController, SettingController, ActivityLogController) and wire routes under the existing `routes/admin.php` protected group, satisfies **AC-1 through AC-21**
12. Update permission seeding (RolePermissionSeeder) with new permissions: view_customers, manage_customers, can_delete_customers, manage_admins, manage_settings, view_activity_logs, view_dashboard. Assign to ADMIN and OWNER roles as described in the security model, satisfies **AC-19**
13. Inject ActivityLogService calls into the existing store oversight service methods (AdminStoreController from spec 0002, built separately) so that store and product admin actions are also logged. This is a small addition to the existing controllers, not a rebuild, satisfies **AC-18**
14. Write feature tests for all new endpoints covering happy paths, permission denials, validation failures, pagination, cascading behavior, and rate limiting, satisfies **AC-5 through AC-21**
15. Run `vendor/bin/pint --format agent` on all new files

## Consequences

**Positive**:
- Complete admin backend with oversight, management, and audit capability.
- Activity log provides accountability and a trail for investigating issues.
- Permission gating at the granular level (can_delete_customers as a separable permission) gives the OWNER flexible control.
- Store oversight from spec 0002 is extended with logging, not duplicated.
- All endpoints use the same auth, permission, and rate limiting patterns as the existing codebase.

**Negative / tradeoffs**:
- Suspending a customer cascading to their stores means an admin can inadvertently hide stores by suspending the owner. This is the intended behavior (a suspended seller should not have visible stores) but it is a strong action.
- Customer delete is a hard delete with no recovery. There is no soft delete or archive step. The `can_delete_customers` permission gate mitigates accidental deletion but does not prevent it for those who have the permission.
- Activity log entries are append only with no retention policy defined. Over time the log table will grow. A retention policy (e.g. delete entries older than 90 days) should be added later.
- OWNER endpoints for admin management have no guard against deleting the last admin. The owner must be careful.

**Neutral**:
- The activity log service hook must be added to the store oversight controllers from spec 0002 as well. This is tracked in the build plan (task 13).
- Existing admin account routes (password change, 2FA, email update) from spec 0001 are unchanged and are not part of this spec.

## Follow-up

- [ ] The `can_delete_customers` permission must be assignable via a future OWNER endpoint or admin panel. This spec provides the permission gate but not the assignment UI/endpoint. The seeding assigns it only to the OWNER role by default.
- [ ] Activity log retention policy: add a scheduled job or database cleanup policy for log entries older than 90 days (or a configurable threshold). Without this, the activity_logs table grows without bound.
- [ ] The activity log should include store and product admin actions from spec 0002. Build task 13 covers injecting the log calls into those controllers after they are built.
- [ ] Ensure the `manage_admins` permission is assigned to the OWNER role in the RolePermissionSeeder update.
