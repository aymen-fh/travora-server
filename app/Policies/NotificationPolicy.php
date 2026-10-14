<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Notification;
use App\Models\User;

final class NotificationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('dashboard.view')
            || $user->can('support.tickets.view')
            || $user->can('notifications.view')
            || $user->can('tickets.view');
    }

    public function view(User $user, Notification $notification): bool
    {
        return $this->viewAny($user);
    }
}
