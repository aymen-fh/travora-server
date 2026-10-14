<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\AuditLog;
use App\Models\User;

final class AuditLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::AuditView->value);
    }

    public function view(User $user, AuditLog $auditLog): bool
    {
        return $user->can(PermissionName::AuditView->value);
    }
}
