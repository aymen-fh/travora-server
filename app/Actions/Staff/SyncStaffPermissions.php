<?php

declare(strict_types=1);

namespace App\Actions\Staff;

use App\Actions\Audit\RecordAuditLog;
use App\Models\User;
use App\Support\Rbac\RolePermissionMatrix;
use App\Support\Rbac\StaffAccess;
use Illuminate\Http\Request;

final class SyncStaffPermissions
{
    public function __construct(
        private readonly StaffAccess $access,
        private readonly RecordAuditLog $audit,
    ) {}

    /**
     * @param  list<string>  $permissions
     * @return array{role_permissions: list<string>, direct_permissions: list<string>, effective_permissions: list<string>}
     */
    public function __invoke(User $actor, User $staff, array $permissions, ?Request $request = null): array
    {
        $this->access->assertCanTouch($actor, $staff);
        $this->access->assertKnownPermissions($permissions);
        $this->access->assertCanGrant($actor, RolePermissionMatrix::expand($permissions));

        $before = RolePermissionMatrix::breakdown($staff);
        $staff->syncPermissions(RolePermissionMatrix::expand($permissions));
        $staff->unsetRelation('permissions');
        $after = RolePermissionMatrix::breakdown($staff->fresh() ?? $staff);

        foreach (array_diff($after['direct_permissions'], $before['direct_permissions']) as $granted) {
            ($this->audit)(
                'staff.permission_granted',
                $actor,
                User::class,
                $staff->id,
                null,
                ['permission' => $granted],
                $request?->ip(),
                $request?->userAgent(),
            );
        }

        foreach (array_diff($before['direct_permissions'], $after['direct_permissions']) as $revoked) {
            ($this->audit)(
                'staff.permission_revoked',
                $actor,
                User::class,
                $staff->id,
                ['permission' => $revoked],
                null,
                $request?->ip(),
                $request?->userAgent(),
            );
        }

        return $after;
    }
}
