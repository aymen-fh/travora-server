<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\SimProduct;
use App\Models\User;

final class SimProductPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::ProductsView->value)
            || $user->can(PermissionName::EsimProductsView->value);
    }

    public function view(User $user, SimProduct $product): bool
    {
        return $user->can(PermissionName::ProductsView->value)
            || $user->can(PermissionName::EsimProductsView->value);
    }
}
