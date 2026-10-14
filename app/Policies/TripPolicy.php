<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\Trip;
use App\Models\User;

final class TripPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::OrdersView->value);
    }

    public function view(User $user, Trip $trip): bool
    {
        return $user->can(PermissionName::OrdersView->value);
    }
}
