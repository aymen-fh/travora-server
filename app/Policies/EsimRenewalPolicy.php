<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\EsimRenewal;
use App\Models\User;

final class EsimRenewalPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::EsimProfilesView->value)
            || $user->can(PermissionName::EsimsView->value);
    }

    public function view(User $user, EsimRenewal $renewal): bool
    {
        return $user->can(PermissionName::EsimProfilesView->value)
            || $user->can(PermissionName::EsimsView->value);
    }
}
