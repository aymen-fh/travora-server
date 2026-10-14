<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\Payment;
use App\Models\User;

final class PaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::PaymentsView->value)
            || $user->can(PermissionName::PaymentsViewDetail->value);
    }

    public function view(User $user, Payment $payment): bool
    {
        return $user->can(PermissionName::PaymentsView->value)
            || $user->can(PermissionName::PaymentsViewDetail->value);
    }

    public function viewAttempts(User $user, Payment $payment): bool
    {
        return $user->can(PermissionName::PaymentsView->value)
            || $user->can(PermissionName::PaymentsViewDetail->value);
    }

    public function refund(User $user, Payment $payment): bool
    {
        return $user->can(PermissionName::PaymentsRefund->value)
            || $user->can(PermissionName::RefundsCreate->value)
            || $user->can(PermissionName::RefundsManage->value);
    }

    public function viewRefunds(User $user, ?Payment $payment = null): bool
    {
        return $user->can(PermissionName::PaymentRefundsView->value)
            || $user->can(PermissionName::PaymentsView->value)
            || $user->can(PermissionName::RefundsView->value);
    }
}
