<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\Order;
use App\Models\User;

final class OrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::OrdersView->value) || $user->isActive();
    }

    public function view(User $user, Order $order): bool
    {
        return $order->user_id === $user->id || $user->can(PermissionName::OrdersView->value);
    }

    public function create(User $user): bool
    {
        return $user->isActive();
    }

    public function update(User $user, Order $order): bool
    {
        return $order->user_id === $user->id || $user->can(PermissionName::OrdersRefund->value);
    }

    public function cancel(User $user, Order $order): bool
    {
        return $order->user_id === $user->id || $user->can(PermissionName::OrdersCancel->value) || $user->can(PermissionName::OrdersManage->value);
    }

    public function retryFulfillment(User $user, Order $order): bool
    {
        return $user->can(PermissionName::OrdersRetryFulfillment->value) || $user->can(PermissionName::OrdersManage->value);
    }
}
