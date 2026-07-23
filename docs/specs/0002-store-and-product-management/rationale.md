# 0002. Store and product management · rationale

## Context

The platform already has customer authentication (spec 0001) with CUSTOMER, ADMIN, and OWNER roles. Customers are authenticated via Sanctum tokens. The project uses the repository service pattern with interfaces and implementations, UUID primary keys, and S3 file storage. Spatie Laravel Permission is installed for role and permission management.

There is a need for customers to have their own stores where they can list products and organize them into categories. The stores are not an ecommerce checkout system. Instead, a customer browses products, sees the price and details, then clicks a button that opens WhatsApp with the store owners phone number and product details pre filled. This means no payment processing, no order management, and no cart on this platform.

The existing CUSTOMER role needs permissions for store management. The ADMIN role needs visibility into stores and products for oversight. The OWNER role has full access across the platform.

## Options considered

### Option 1: Direct ownership model with Spatie Permission

Each store belongs to a user via user_id. Permissions like manage own products and manage own categories are granted to the CUSTOMER role. The controller checks ownership by comparing the store's user_id with the authenticated user. Admins get view_stores, manage_stores, and view_products permissions.

**Pros**:
- Simple and clear. No complex role hierarchies to maintain.
- Ownership check is a single WHERE clause, not a permission evaluation.
- Works with the existing CUSTOMER, ADMIN, and OWNER role structure.

**Cons**:
- Adding a store manager role later (someone who manages a store but is not the owner) would require refactoring the ownership check into a permission.

### Option 2: Full Spatie Permission per store

Every store gets its own permission or role. Store owners are assigned manage_products_{store_id} and manage_categories_{store_id} permissions in the database.

**Pros**:
- Granular control. A store can have multiple managers without code changes.
- Audit trail through the permission assignments.

**Cons**:
- Thousands of permissions over time (one set per store). Bloat in the permissions table.
- More complex permission seeding and cleanup when a store is deleted.
- Not justified by the current requirement (one owner per store).

### Option 3: Single STORE_OWNER role

Create a new STORE_OWNER role that grants store management permissions. Customers are upgraded from CUSTOMER to STORE_OWNER when they create a store.

**Pros**:
- Clear role boundary between a customer without a store and one with a store.
- Easy to add store management permissions to this role.

**Cons**:
- A separate role for what is essentially ownership by user_id adds complexity without benefit.
- Permission checks still need ownership validation to prevent one STORE_OWNER from editing another's store.

## Decision

**Chosen option**: Option 1: Direct ownership model with Spatie Permission

Store access is controlled by ownership (user_id on the store record). The CUSTOMER role gets the manage own stores, manage own products, and manage own categories permissions so the system can check for these at the API middleware or gate level. The ADMIN role gets view_stores, manage_stores (suspend/activate), and view_products. The OWNER role implicitly has everything.

**Implementation skills**: `backend-development-rules` (`l0n3ly/laravel-boost`, `.agents/skills/backend-development-rules/`) defines the repository service pattern, controllers, form requests, resources, enums, and code quality standards this feature must follow. `laravel-permission-development` (`.agents/skills/laravel-permission-development/`) defines the permission seeding and role assignment pattern. `helper-creation` (`.agents/skills/helper-creation/`) defines the image processing helper pattern.

## Rationale

Ownership based access (Option 1) is the simplest approach that meets the requirement. Each store has exactly one owner, the user who created it. Checking user_id is a single database query, not a permission evaluation. It avoids the permission bloat of Option 2 (one permission per store per action) and the unnecessary role distinction of Option 3 (every store owner is still a customer).

The existing CUSTOMER role needs new permissions for store management, but these are global permissions (can manage own stores, can manage own products, can manage own categories). The ownership check inside each controller or service ensures one customer cannot touch another's data. This two layer model (global permission + ownership filter) is the standard pattern for multi tenant data in a single schema.

If the product later needs delegated store managers (people who manage a store but are not the owner), that is a natural extension: add a store_user pivot table with role assignments and check that instead of user_id. That change is contained in the service layer and does not change the API surface or data model.
