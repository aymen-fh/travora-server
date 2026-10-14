<?php

declare(strict_types=1);

namespace App\Support\Rbac;

use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

final class StaffAccess
{
    public function isSuperAdmin(User $user): bool
    {
        return $user->hasRole(RoleName::SuperAdmin->value);
    }

    public function assertStaff(User $user): void
    {
        if (! $user->hasAnyRole(RolePermissionMatrix::staffRoleNames())) {
            abort(404);
        }
    }

    public function assertCanTouch(User $actor, User $target): void
    {
        $this->assertStaff($target);

        if ($this->isSuperAdmin($target) && ! $this->isSuperAdmin($actor)) {
            throw new AuthorizationException('Forbidden.');
        }
    }

    public function assertCanAssignRole(User $actor, string $role): void
    {
        if (! in_array($role, RolePermissionMatrix::assignableStaffRoles(), true)) {
            throw new AuthorizationException('Forbidden.');
        }

        if ($role === RoleName::SuperAdmin->value && ! $this->isSuperAdmin($actor)) {
            throw new AuthorizationException('Forbidden.');
        }

        if ($this->isSuperAdmin($actor)) {
            return;
        }

        foreach (RolePermissionMatrix::forRole(RoleName::from($role)) as $permission) {
            if (! $actor->can($permission)) {
                throw new AuthorizationException('Forbidden.');
            }
        }
    }

    /**
     * @param  list<string>  $permissions
     */
    public function assertCanGrant(User $actor, array $permissions): void
    {
        if ($this->isSuperAdmin($actor)) {
            return;
        }

        foreach ($permissions as $permission) {
            if (! $actor->can($permission)) {
                throw new AuthorizationException('Forbidden.');
            }
        }
    }

    /**
     * @param  list<string>  $permissions
     */
    public function assertKnownPermissions(array $permissions): void
    {
        $known = Permission::query()->where('guard_name', 'web')->whereIn('name', $permissions)->pluck('name')->all();
        $unknown = array_values(array_diff($permissions, $known));

        if ($unknown !== []) {
            throw new AuthorizationException('Forbidden.');
        }
    }

    public function assertCanEditRole(User $actor, Role $role): void
    {
        if ($role->name === RoleName::SuperAdmin->value && ! $this->isSuperAdmin($actor)) {
            throw new AuthorizationException('Forbidden.');
        }

        if ($role->name === RoleName::User->value) {
            throw new AuthorizationException('Forbidden.');
        }
    }

    public function assertLastSuperAdminRemains(User $target, ?string $nextRole = null, ?UserStatus $nextStatus = null): void
    {
        if (! $this->isSuperAdmin($target) || $target->status !== UserStatus::Active) {
            return;
        }

        $demoting = $nextRole !== null && $nextRole !== RoleName::SuperAdmin->value;
        $suspending = $nextStatus !== null && $nextStatus !== UserStatus::Active;

        if (! $demoting && ! $suspending) {
            return;
        }

        $remaining = User::query()
            ->role(RoleName::SuperAdmin->value)
            ->where('status', UserStatus::Active)
            ->whereKeyNot($target->id)
            ->count();

        if ($remaining < 1) {
            throw new AuthorizationException('Forbidden.');
        }
    }
}
