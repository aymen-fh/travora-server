<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\SupportTicket;
use App\Models\User;

final class SupportTicketPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::SupportTicketsView->value)
            || $user->can(PermissionName::SupportManage->value)
            || $user->can(PermissionName::TicketsView->value)
            || $user->can(PermissionName::TicketsViewDetail->value);
    }

    public function view(User $user, SupportTicket $ticket): bool
    {
        return $user->can(PermissionName::SupportTicketsView->value)
            || $user->can(PermissionName::SupportManage->value)
            || $user->can(PermissionName::TicketsView->value)
            || $user->can(PermissionName::TicketsViewDetail->value);
    }

    public function assign(User $user, SupportTicket $ticket): bool
    {
        return $user->can(PermissionName::SupportTicketsAssign->value)
            || $user->can(PermissionName::SupportManage->value)
            || $user->can(PermissionName::TicketsAssign->value);
    }

    public function reply(User $user, SupportTicket $ticket): bool
    {
        return $user->can(PermissionName::SupportTicketsReply->value)
            || $user->can(PermissionName::SupportManage->value)
            || $user->can(PermissionName::TicketsUpdate->value);
    }

    public function close(User $user, SupportTicket $ticket): bool
    {
        return $user->can(PermissionName::SupportTicketsClose->value)
            || $user->can(PermissionName::SupportManage->value)
            || $user->can(PermissionName::TicketsClose->value);
    }
}
