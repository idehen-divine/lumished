# Store and product management

**Status**: in-progress

**Intent**: Customers can create and manage their own stores, categories, and products. Visitors can browse stores and products publicly. Purchases redirect to WhatsApp. Admins oversee stores and can suspend them.

**Done when**: Customers can create stores, manage categories (with subcategories) and products, upload images that convert to webp, and delete their stores. Public browsing works without auth. Admins can list all stores, view products, and manage store status. All endpoints are tested.

**Spec**: [0002](../specs/0002-store-and-product-management/index.md)

## Sub-tasks

- [x] Design it (spec)
- [x] Build it: /develop store
  - [x] Data layer: migrations, models with HasUuids and casts, repositories, API resources, form requests (satisfies AC-1, AC-3, AC-4)
  - [x] Image processing helper using spatie/image with webp conversion at 85% quality and S3 upload with temp key pattern (satisfies AC-5)
  - [x] Services: StoreService, CategoryService, ProductService with ownership checks, category tree, store deletion cascade (satisfies AC-2, AC-3, AC-4, AC-7, AC-13)
  - [x] Controllers, routes (public, customer, admin), permissions seeding, middleware (satisfies AC-1 through AC-11, AC-13)
  - [x] Full feature tests (satisfies AC-1 through AC-14)
- [ ] Verify it: /check verify store
- [ ] Test it: /test store
