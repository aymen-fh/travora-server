<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\NetworkOperator;
use App\Models\User;

final class NetworkOperatorPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::EsimPlansView->value)
            || $user->can(PermissionName::NetworkOperatorsView->value);
    }

    public function view(User $user, NetworkOperator $operator): bool
    {
        return $user->can(PermissionName::EsimPlansView->value)
            || $user->can(PermissionName::NetworkOperatorsView->value);
    }
}
