<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Support\Rbac\RolePermissionMatrix;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Local and test bootstrap for the permission catalog.
 * Production permission changes belong in a migration, not `db:seed`.
 */
final class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (PermissionName::cases() as $permission) {
            Permission::findOrCreate($permission->value, 'web');
        }

        foreach (RoleName::cases() as $roleName) {
            $role = Role::findOrCreate($roleName->value, 'web');
            $role->syncPermissions(RolePermissionMatrix::forRole($roleName));
            if (! filled($role->description)) {
                $role->description = RolePermissionMatrix::description($roleName);
                $role->save();
            }
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
