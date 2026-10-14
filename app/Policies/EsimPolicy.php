<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\EsimProfile;
use App\Models\User;

final class EsimPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isActive()
            || $user->can(PermissionName::EsimProfilesView->value)
            || $user->can(PermissionName::EsimsView->value);
    }

    public function view(User $user, EsimProfile $esim): bool
    {
        return $esim->user_id === $user->id
            || $user->can(PermissionName::EsimProfilesView->value)
            || $user->can(PermissionName::EsimsView->value)
            || $user->can(PermissionName::OrdersView->value);
    }

    public function viewUsage(User $user, EsimProfile $esim): bool
    {
        return $esim->user_id === $user->id
            || $user->can(PermissionName::EsimUsageView->value)
            || $user->can(PermissionName::EsimsViewDetail->value);
    }

    public function syncUsage(User $user, EsimProfile $esim): bool
    {
        return $user->can(PermissionName::EsimUsageSync->value) || $user->can(PermissionName::EsimsManage->value);
    }

    public function command(User $user, EsimProfile $esim): bool
    {
        return $user->can(PermissionName::EsimsManage->value);
    }

    public function sendSms(User $user, EsimProfile $esim): bool
    {
        return $user->can(PermissionName::EsimsManage->value);
    }
}
