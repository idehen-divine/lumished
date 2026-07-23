<?php

namespace Database\Seeders;

use App\Enums\PermissionEnum;
use App\Enums\RoleEnum;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (PermissionEnum::cases() as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        foreach (RoleEnum::cases() as $role) {
            Role::firstOrCreate(['name' => $role]);
        }

        $ownerRole = Role::findByName(RoleEnum::OWNER->name);
        $adminRole = Role::findByName(RoleEnum::ADMIN->name);
        $customerRole = Role::findByName(RoleEnum::CUSTOMER->name);

        $ownerRole->givePermissionTo(Permission::all());
        $adminRole->givePermissionTo([
            PermissionEnum::ACCESS_ADMIN_PANEL->name,
            PermissionEnum::VIEW_PRODUCTS->name,
            PermissionEnum::EDIT_PRODUCTS->name,
            PermissionEnum::VIEW_CATEGORIES->name,
            PermissionEnum::EDIT_CATEGORIES->name,
            PermissionEnum::VIEW_BRANDS->name,
            PermissionEnum::EDIT_BRANDS->name,
            PermissionEnum::VIEW_CUSTOMERS->name,
            PermissionEnum::EDIT_CUSTOMERS->name,
            PermissionEnum::VIEW_ORDERS->name,
            PermissionEnum::MANAGE_ORDERS->name,
            PermissionEnum::VIEW_LOGS->name,
            PermissionEnum::VIEW_STORES->name,
            PermissionEnum::MANAGE_STORES->name,
        ]);
        $customerRole->givePermissionTo([
            PermissionEnum::ACCESS_CUSTOMER_PANEL->name,
            PermissionEnum::VIEW_PRODUCTS->name,
            PermissionEnum::VIEW_ORDERS->name,
            PermissionEnum::MANAGE_OWN_STORES->name,
            PermissionEnum::MANAGE_OWN_PRODUCTS->name,
            PermissionEnum::MANAGE_OWN_CATEGORIES->name,
        ]);
    }
}
