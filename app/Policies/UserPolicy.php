<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\User;

final class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can(PermissionName::UsersView->value);
    }

    public function view(User $actor, User $user): bool
    {
        return $actor->can(PermissionName::UsersView->value);
    }

    public function update(User $actor, User $user): bool
    {
        return $actor->can(PermissionName::UsersUpdate->value);
    }

    public function suspend(User $actor, User $user): bool
    {
        return $actor->can(PermissionName::UsersSuspend->value);
    }

    public function viewSessions(User $actor, User $user): bool
    {
        return $actor->can(PermissionName::UsersSessionsView->value);
    }

    public function revokeSession(User $actor, User $user): bool
    {
        return $actor->can(PermissionName::UsersSessionsRevoke->value);
    }
}
