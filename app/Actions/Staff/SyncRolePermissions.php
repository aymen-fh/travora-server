<?php

declare(strict_types=1);

namespace App\Actions\Staff;

use App\Actions\Audit\RecordAuditLog;
use App\Enums\RoleName;
use App\Models\User;
use App\Support\Rbac\RolePermissionMatrix;
use App\Support\Rbac\StaffAccess;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

final class SyncRolePermissions
{
    public function __construct(
        private readonly StaffAccess $access,
        private readonly RecordAuditLog $audit,
    ) {}

    /**
     * @param  list<string>  $permissions
     * @return list<string>
     */
    public function __invoke(User $actor, Role $role, array $permissions, ?Request $request = null): array
    {
        $this->access->assertCanEditRole($actor, $role);

        if ($role->name === RoleName::SuperAdmin->value) {
            throw new AuthorizationException('Forbidden.');
        }

        $this->access->assertKnownPermissions($permissions);
        $expanded = RolePermissionMatrix::expandForRole($role->name, $permissions);
        $this->access->assertCanGrant($actor, $expanded);

        $before = RolePermissionMatrix::visiblePermissions($role->permissions->pluck('name')->all());
        $role->syncPermissions($expanded);
        $role->unsetRelation('permissions');
        $role->load('permissions');
        $after = RolePermissionMatrix::visiblePermissions($role->permissions->pluck('name')->all());

        foreach (array_diff($after, $before) as $granted) {
            ($this->audit)(
                'role.permission_granted',
                $actor,
                'role',
                null,
                null,
                ['role' => $role->name, 'role_id' => $role->id, 'permission' => $granted],
                $request?->ip(),
                $request?->userAgent(),
            );
        }

        foreach (array_diff($before, $after) as $revoked) {
            ($this->audit)(
                'role.permission_revoked',
                $actor,
                'role',
                null,
                ['role' => $role->name, 'role_id' => $role->id, 'permission' => $revoked],
                null,
                $request?->ip(),
                $request?->userAgent(),
            );
        }

        return $after;
    }
}
