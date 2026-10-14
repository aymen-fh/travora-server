<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\Promotion;
use App\Models\User;

final class PromotionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::ProductsView->value)
            || $user->can(PermissionName::PromotionsView->value);
    }

    public function view(User $user, Promotion $promotion): bool
    {
        return $user->can(PermissionName::ProductsView->value)
            || $user->can(PermissionName::PromotionsView->value);
    }
}
