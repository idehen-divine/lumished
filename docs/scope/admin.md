# Admin management

**Status**: in-progress

**Intent**: Full admin dashboard with customer management, OWNER-level admin management, dashboard statistics, system settings, activity audit log, and store oversight.

**Done when**: Admins can manage customer accounts (list, view, update, suspend, delete with permission). OWNER can create, update, and delete admin users. Dashboard returns platform counts. System settings are configurable via enum-keyed endpoints. All admin write actions are recorded in the activity log. All endpoints are tested.

**Spec**: [0003](../specs/0003-admin-management-api.md)

## Sub-tasks

- [x] Design it (spec)
- [ ] Build it: /develop admin
  - [ ] Data layer: ActivityLog and SystemSetting migrations, models, ActivityLogRepository, SettingKey enum (satisfies AC-15, AC-18)
  - [ ] Services and helpers: ActivityLogService, AdminCustomerService, AdminUserService, DashboardService, SettingHelper (satisfies AC-5 through AC-18, AC-21)
  - [ ] API layer: form requests, API resources, controllers, routes, permission seeding (satisfies AC-1 through AC-21)
  - [ ] Activity log integration into store oversight controllers + full feature tests (satisfies AC-18, AC-5 through AC-21)
- [ ] Verify it: /check verify admin
- [ ] Test it: /test admin
