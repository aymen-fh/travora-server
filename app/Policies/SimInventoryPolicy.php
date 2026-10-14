<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\SimInventory;
use App\Models\User;

final class SimInventoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::ProductsView->value)
            || $user->can(PermissionName::SimInventoryView->value);
    }

    public function view(User $user, SimInventory $inventory): bool
    {
        return $user->can(PermissionName::ProductsView->value)
            || $user->can(PermissionName::SimInventoryView->value);
    }
}
