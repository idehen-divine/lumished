# Verify: Store and product management · spec 0002 · updated 2026-07-22
_Steps derived from spec 0002 acceptance criteria. `/check verify` runs these; `/test` locks the durable ones._

## Commands

- [ ] `php artisan test --compact --filter="Store|Category|Product"` → 94 tests pass          → AC-1 through AC-14
- [ ] `php artisan test --compact` → all 181 tests pass                                        → regression
- [ ] `vendor/bin/pint --dirty --format agent` → no style violations                             → code quality

## Acceptance-criteria coverage

- AC-1 covered by Store CreateTest (create, slug auto generate, defaults to active)
- AC-2 covered by Store GetTest + UpdateTest (view own stores, update fields)
- AC-3 covered by Category CreateTest + DeleteTest (hierarchy, reparent, unassign)
- AC-4 covered by Product CreateTest + DeleteTest (CRUD, status defaults to draft)
- AC-5 covered by ImageHelper (webp conversion, S3 storage, temp key pattern)
- AC-6 covered by Public StoreGetTest (active stores only, published products only)
- AC-7 covered by all ownership denial tests (another user's store returns 403/404)
- AC-8 covered by Admin Store GetTest + UpdateTest (list all, status update)
- AC-9 covered by Store CreateTest (duplicate slug appends number)
- AC-10 covered by Product CreateTest (zero price accepted, negative rejected)
- AC-11 covered by route middleware (auth:sanctum on customer, public throttle)
- AC-12 covered by pagination assertions in Store GetTest + Public StoreGetTest
- AC-13 covered by Store DeleteTest (cascade deletes products, categories, pivots)
- AC-14 covered by RateLimitTest in auth tests + throttle middleware on public routes

## Next

- [ ] `/check verify store` — production verification
- [ ] `/test store` — lock durable test scenarios
