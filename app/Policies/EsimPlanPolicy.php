<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\EsimPlan;
use App\Models\EsimPlanPrice;
use App\Models\User;

final class EsimPlanPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::EsimPlansView->value)
            || $user->can(PermissionName::EsimProductsView->value);
    }

    public function view(User $user, EsimPlan $plan): bool
    {
        return $user->can(PermissionName::EsimPlansView->value)
            || $user->can(PermissionName::EsimProductsView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionName::EsimPlansManage->value)
            || $user->can(PermissionName::EsimProductsCreate->value)
            || $user->can(PermissionName::EsimProductsUpdate->value);
    }

    public function update(User $user, EsimPlan $plan): bool
    {
        return $user->can(PermissionName::EsimPlansManage->value)
            || $user->can(PermissionName::EsimProductsCreate->value)
            || $user->can(PermissionName::EsimProductsUpdate->value);
    }

    public function viewPrices(User $user, EsimPlan $plan): bool
    {
        return $user->can(PermissionName::EsimPricesView->value)
            || $user->can(PermissionName::EsimProductsView->value);
    }

    public function managePrices(User $user, EsimPlan $plan): bool
    {
        return $user->can(PermissionName::EsimPricesManage->value)
            || $user->can(PermissionName::EsimProductsUpdate->value);
    }

    public function deactivatePrice(User $user, EsimPlanPrice $price): bool
    {
        return $user->can(PermissionName::EsimPricesManage->value)
            || $user->can(PermissionName::EsimProductsUpdate->value);
    }
}
