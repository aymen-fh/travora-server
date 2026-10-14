<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\EsimTopup;
use App\Models\User;

final class EsimTopupPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::EsimProfilesView->value)
            || $user->can(PermissionName::TopupsView->value)
            || $user->can(PermissionName::EsimsView->value);
    }

    public function view(User $user, EsimTopup $topup): bool
    {
        return $user->can(PermissionName::EsimProfilesView->value)
            || $user->can(PermissionName::TopupsView->value)
            || $user->can(PermissionName::EsimsView->value);
    }
}
