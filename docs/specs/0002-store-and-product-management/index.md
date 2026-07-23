# 0002. Store and product management

**Date**: 2026-07-21
**Status**: In Progress

## Summary

Customers can create and manage their own stores on the platform. Each store has its own categories (with subcategories) and products. Anyone can browse stores and products publicly without logging in. Purchases happen through WhatsApp redirect (no payment handling on this platform). Admins can oversee stores and suspend them when needed. The feature follows the existing repository service pattern and uses Spatie Permission for access control.

## Requirements

**User stories**:
- As a customer, I want to create and manage my own store so that I can list my products
- As a customer, I want to organize my products into categories with subcategories so that customers can browse easily
- As a visitor, I want to browse stores and products publicly without needing to log in
- As a visitor, I want to contact the store owner on WhatsApp with product details so that I can buy
- As an admin, I want to see all stores and products and suspend stores when needed

**Acceptance criteria** (the contract, each criterion is independently checkable):
- **AC-1**: A customer can create a store with name, description, logo, and contact info. Slug auto generates from the name and is globally unique. Store status defaults to active.
- **AC-2**: A customer can view and update their own stores. They can update all fields including uploading a new logo.
- **AC-3**: A customer can create, update, delete, and list categories within one of their stores. Categories support a parent child hierarchy (subcategories). Deleting a category reparents its subcategories up one level and unassigns its products from that category only.
- **AC-4**: A customer can create, update, delete, and list products within one of their stores. Each product belongs to one or more categories in the same store. Products have a status of draft, published, or archived.
- **AC-5**: Product images (1 main photo plus up to 3 extra) are uploaded as part of the create or update request. Images are converted to webp at 85% quality and stored on S3. Max 5MB per file. Accepted formats are jpg, png, and webp.
- **AC-6**: Published products and store information are publicly readable without authentication. Anyone can browse stores, products, and categories.
- **AC-7**: Only the store owner can manage that store's products and categories. Permission checks use the store's user_id, not a separate role assignment.
- **AC-8**: Admins can list all stores, view products in any store, and update a store's status (suspend or activate). Admins cannot create or edit products or categories.
- **AC-9**: Store slugs are globally unique. Product stock is informational and display only, not tracked or deducted.
- **AC-10**: Prices display in NGN currency by default. A product can be priced at 0 (free listing) or any positive number.
- **AC-11**: All public endpoints are rate limited. Customer management endpoints require authentication via Sanctum.
- **AC-12**: All list endpoints return paginated results using page based pagination with a default of 15 items per page and a maximum of 100.
- **AC-13**: A customer can delete their store. Deleting a store also deletes all its products, categories, and pivot rows. Only the store owner can delete the store.
- **AC-14**: Public endpoints are rate limited to 60 requests per minute. Authenticated customer endpoints are rate limited to 30 requests per minute.

See [`rationale.md`](rationale.md) for design decisions and alternatives considered.

## Feature design

**Data model sketch**:

**Store**
- id: uuid (primary key)
- user_id: uuid (FK to users), required
- name: string (255), required
- slug: string (255), required, globally unique
- description: text, nullable
- tagline: string (255), nullable
- logo_url: string (255), nullable (S3 URL)
- currency: string (3), default NGN
- phone: string (50), nullable
- email: string (255), nullable
- address: text, nullable
- whatsapp_number: string (50), required (purchase redirect target)
- status: enum (active, inactive, suspended), default active
- timestamps

**Category**
- id: uuid (primary key)
- store_id: uuid (FK to stores), required
- parent_id: uuid (nullable, FK to self, for subcategories)
- name: string (255), required
- slug: string (255), required, unique per store
- description: text, nullable
- timestamps
- Unique constraint: (store_id, slug)

**Product**
- id: uuid (primary key)
- store_id: uuid (FK to stores), required
- name: string (255), required
- description: text, nullable
- price: decimal (10,2), required, min 0
- compare_at_price: decimal (10,2), nullable
- stock_quantity: integer, default 0 (informational, not tracked)
- photo: string (255), nullable (main image S3 URL)
- photos: json, nullable (array of up to 3 extra S3 URLs)
- status: enum (draft, published, archived), default draft
- timestamps

**category_product** pivot
- category_id: uuid (FK to categories)
- product_id: uuid (FK to products)
- Primary key: (category_id, product_id)

**State transitions**:
- Product: draft -> published -> archived (published can go back to draft; archived is terminal unless explicitly restored)
- Store: active -> suspended (admin action), suspended -> active (admin action)

**API surface**:

**Public endpoints (no auth)**:
| Endpoint | Method | Key inputs | Key outputs | Auth | Key errors |
|---|---|---|---|---|---|
| /api/v1/stores | GET | page, per_page | paginated active store list | public | 422 invalid pagination |
| /api/v1/stores/{slug} | GET | slug:str(path) | store with products count, whatsapp_number | public | 404 not found |
| /api/v1/stores/{slug}/products | GET | slug:str(path), category_id(opt), page, per_page | paginated published product list, each includes store slug and whatsapp_number | public | 404 store, 422 invalid pagination |
| /api/v1/stores/{slug}/categories | GET | slug:str(path) | category tree | public | 404 store |
| /api/v1/products/{id} | GET | id:uuid(path) | product details with store slug and whatsapp_number | public | 404 product |

**Customer endpoints (auth:sanctum, customer owns the store)**:
| Endpoint | Method | Key inputs | Key outputs | Auth | Key errors |
|---|---|---|---|---|---|
| /api/v1/customer/stores | POST | name:str(req), tagline:str(opt), description:text(opt), logo:file(opt), phone:str(opt), email:str(opt), address:text(opt), whatsapp_number:str(req) | store (includes tagline, currency default NGN) | bearer | 422 validation |
| /api/v1/customer/stores | GET | page, per_page | paginated own stores list | bearer | none |
| /api/v1/customer/stores/{id} | GET | id:uuid(path) | store details | bearer | 403 not owner, 404 |
| /api/v1/customer/stores/{id} | PUT | same as create, all fields optional. Omitting a file field keeps the existing image; sending null clears it. | updated store | bearer | 403 not owner, 422 |
| /api/v1/customer/stores/{id} | DELETE | id:uuid(path) | message | bearer | 403 not owner, 404 |
| /api/v1/store/{storeSlug}/products | GET | storeSlug:str(path), page, per_page, status(opt), category_id(opt) | paginated own products | bearer | 403 not owner |
| /api/v1/store/{storeSlug}/products | POST | name:str(req), description:text(opt), price:decimal(req), compare_at_price:decimal(opt), stock_quantity:int(opt), photo:file(opt), photos:file[](opt, max 3), status:str(opt), category_ids:uuid[](req, at least 1) | product | bearer | 403 not owner, 422 |
| /api/v1/store/{storeSlug}/products/{id} | GET | storeSlug, id:uuid | product | bearer | 403 not owner, 404 |
| /api/v1/store/{storeSlug}/products/{id} | PUT | same as create, all optional. Omitting a file field keeps the existing image. | updated product | bearer | 403 not owner, 422 |
| /api/v1/store/{storeSlug}/products/{id} | DELETE | storeSlug, id:uuid | message | bearer | 403 not owner, 404 |
| /api/v1/store/{storeSlug}/categories | GET | storeSlug:str(path) | category tree | bearer | 403 not owner |
| /api/v1/store/{storeSlug}/categories | POST | name:str(req), parent_id:uuid(opt), description:text(opt) | category | bearer | 403 not owner, 422 |
| /api/v1/store/{storeSlug}/categories/{id} | GET | storeSlug, id:uuid | category | bearer | 403 not owner, 404 |
| /api/v1/store/{storeSlug}/categories/{id} | PUT | name:str(opt), parent_id:uuid(opt), description:text(opt) | updated category | bearer | 403 not owner, 422 |
| /api/v1/store/{storeSlug}/categories/{id} | DELETE | storeSlug, id:uuid | message | bearer | 403 not owner, 404 |

**Admin endpoints (auth:sanctum + admin middleware)**:
| Endpoint | Method | Key inputs | Key outputs | Auth | Key errors |
|---|---|---|---|---|---|
| /api/v1/admin/stores | GET | page, per_page, status(opt) | paginated all stores | admin | 403 |
| /api/v1/admin/stores/{id} | GET | id:uuid(path) | store with products | admin | 403, 404 |
| /api/v1/admin/stores/{id}/status | PUT | status:str(req, active/suspended) | updated store | admin | 403, 422 |
| /api/v1/admin/store/{storeSlug}/products | GET | storeSlug:str(path), page, per_page | paginated products | admin | 403, 404 |

**Value sourcing** (every value each action produces names its source):
| Action | Value produced / displayed | Source |
|---|---|---|
| Create store | slug | auto generated from name via Str::slug, checked for global uniqueness |
| Create store | status | hardcoded to active |
| Create store | logo_url | uploaded file, converted to webp 85% quality, stored on S3 via Laravel filesystem |
| Create product | photo, photos | uploaded files, each converted to webp 85% quality, stored on S3 |
| Create product | price | input param, decimal 10,2 |
| Create product | stock_quantity | input param, default 0 |
| Browse public stores | store list | DB, filtered by store status=active, sorted by creation date |
| Browse public products | products list | DB, filtered by status=published and store status=active. Optional filter by category_id |
| Browse public products | store.whatsapp_number | returned in each product entry so the client can build the WhatsApp link |
| Browse public product detail | product detail with store info | DB, joins store to include store slug, name, and whatsapp_number |
| Category delete | reparented children | parent_id updated to deleted category's parent_id |
| Category delete | unassigned products | pivot rows for this category removed |
| Admin suspend store | status changed | PUT /api/v1/admin/stores/{id}/status, only active/suspended as valid values |
| Delete store | cascaded delete | all products, categories, and pivot rows for this store are deleted |
| Store update with images | keep existing image on omit | server checks if the file field was present in the request. If absent, the stored URL is preserved. |

**Key invariants**:
- Store slug is globally unique. Auto generated from name using Str::slug, appended with a number if the slug exists.
- Category slug is unique within a store. Auto generated from name on create. Updating the name does NOT regenerate the slug.
- Product price must be 0 or a positive decimal. Compare at price can be null or higher than price.
- A store can have at most one logo image. A product can have at most one main photo and up to 3 extra photos.
- Parent_id must point to a category in the same store. A category cannot be its own parent (prevent circular reference).
- Published products are only visible if the store status is active.
- Deleting a category reparents its children to the deleted category's parent (or sets parent_id to null if the deleted category had no parent). The category is unassigned from its products (pivot rows deleted).
- A product must have at least one category assigned (category_ids is required on create, minimum 1).
- Omitting a file field (photo, photos, logo) on update preserves the existing stored value. The field must be explicitly set to null to clear it.
- Deleting a store cascades: all its products, categories, and pivot rows are deleted.

**Security model**:
| Role | Store visibility | Products visibility | Can create store | Can manage products | Can manage categories | Admin actions |
|---|---|---|---|---|---|---|
| Guest (no auth) | Active stores only, public | Published products only, public | No | No | No | No |
| Customer (no store) | Own stores only | No store yet | Yes | No | No | No |
| Customer (has stores) | Own stores (manage) + public stores (read) | Own products (manage) + public products (read) | Yes | Own stores only | Own stores only | No |
| Admin | All stores, read + status control | All products, read only | No | No | No | View stores, manage store status, view products |
| Owner | All stores, full control | All products, full control | Yes | All stores | All stores | Everything |

**Configuration required**:
- `FILESYSTEM_DISK`: set to `s3` for production (already configured in the project)
- `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_DEFAULT_REGION`, `AWS_BUCKET`: S3 credentials (already configured for existing S3 usage)
- `S3_IMAGE_PREFIX`: optional, prefix path for uploaded images (e.g. `stores/`)

**Critical test scenarios** (each maps to an acceptance criterion):
- Happy path: customer creates store, adds categories, creates published products, public visitor can browse them, verifies AC-1, AC-2, AC-4, AC-6
- Product images: create product with main photo and 3 extras, verify upload and webp conversion, verifies AC-5
- Category hierarchy: create parent category, add child category, list returns tree structure, verifies AC-3
- Category deletion with children and products: delete a category that has subcategories and assigned products, verify children reparented and products unassigned, verifies AC-3
- Permission: customer A cannot manage customer B's store, verifies AC-7
- Admin: admin lists all stores, suspends a store, verifies AC-8
- Public rate limiting: hitting public product endpoint rapidly returns 429, verifies AC-11
- Pagination: list endpoint returns paginated results with page based params, verifies AC-12
- Store slug uniqueness: creating a store with a duplicate slug (or name that generates a duplicate slug) appends a number, verifies AC-9

## Build plan

Build approach: Tracer Bullet (end to end thin vertical slices through every layer). Each task delivers a working slice from migration through test.

1. Create migrations for stores, categories (with parent_id self FK), products, and the category_product pivot table, satisfies AC-1, AC-3, AC-4
2. Create Store model with HasUuids, enum casts (status), relationships (belongs to User, has many Products, has many Categories), and slug generation logic, satisfies AC-1, AC-9
3. Create Category model with HasUuids, self referential parent/children relationship, unique constraint scope on store_id + slug, satisfies AC-3
4. Create Product model with HasUuids, enum casts (status), casts for photos (JSON), belongs to many Categories, satisfies AC-4
5. Create repositories (StoreRepository, CategoryRepository, ProductRepository) with interfaces and implementations following the existing UserRepository pattern, satisfies AC-1, AC-3, AC-4
6. Create API resources (StoreResource, CategoryResource, ProductResource) matching the existing UserResource pattern, satisfies AC-1
7. Create form requests for all endpoints (CreateStoreRequest, UpdateStoreRequest, CreateProductRequest, UpdateProductRequest, CreateCategoryRequest, UpdateCategoryRequest, UpdateStoreStatusRequest), satisfies AC-1, AC-3, AC-4
8. Create the image processing helper using spatie/image to convert uploaded files to webp at 85% quality and store on S3 via the Laravel filesystem. Use a temp upload pattern: upload to a temp key first, store the temp key in the DB, move to the final key only after the DB write succeeds. If the DB write fails, the temp key can be cleaned up. This avoids orphaned images or lost originals, satisfies AC-5
9. Create services (StoreService, CategoryService, ProductService) with interfaces and implementations containing business logic including ownership checks, category tree building, store deletion cascade, and product filtering, satisfies AC-2, AC-3, AC-4, AC-7, AC-13
10. Create controllers (StoreController, ProductController, CategoryController, AdminStoreController, PublicStoreController) for public, customer, and admin route groups. Public store listing endpoint at GET /api/v1/stores returns only active stores. Public product listing supports optional category_id filter and returns store slug and whatsapp_number with each product. Product detail returns store slug and whatsapp_number, satisfies AC-1 through AC-8, AC-13
11. Define and seed permissions: CUSTOMER role gets manage_own_stores, manage_own_products, manage_own_categories. ADMIN role gets view_stores, manage_stores, view_products. Wire route middleware accordingly, satisfies AC-8
12. Write feature tests for all endpoints covering happy paths, permission denial, validation failures, pagination, store deletion cascade, and rate limiting, satisfies AC-1 through AC-14
13. Run vendor/bin/pint --dirty --format agent on all new files

## Consequences

**Positive**:
- Simple ownership model is easy to understand and implement. No complex permission evaluation for the common case.
- Public browsing requires no auth, reducing friction for visitors.
- WhatsApp redirect model keeps the platform out of payment processing and order fulfillment complexity.
- Image processing helper reduces storage costs and improves page load times through webp conversion.

**Negative / tradeoffs**:
- Adding a delegated store manager later will require a pivot table and refactoring the ownership check.
- Stock quantity is informational only, which means visitors cannot rely on real time availability.
- Image uploads as part of create/update requests increase request size and processing time compared to separate upload endpoints.

**Neutral**:
- New permissions need to be seeded before customer store creation can work.
- spatie/image needs to be added to composer.json.
- Store slugs need collision handling on creation.

## Follow-up

- [ ] spatie/image is not yet installed; add to composer.json before build task 8
- [ ] The image processing helper should be created using the helper-creation skill. File path example: app/Helpers/ImageHelper.php with a public method like storeImage(UploadedFile $file, string $path) that converts to webp at 85% quality and returns the S3 URL
- [ ] Ensure S3 bucket has a policy for public read access on uploaded images
- [ ] Consider adding rate limiting to store creation to prevent abuse (separate from general API rate limiting)
