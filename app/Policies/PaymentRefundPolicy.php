<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\PaymentRefund;
use App\Models\User;

final class PaymentRefundPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::PaymentRefundsView->value)
            || $user->can(PermissionName::RefundsView->value);
    }

    public function view(User $user, PaymentRefund $refund): bool
    {
        return $user->can(PermissionName::PaymentRefundsView->value)
            || $user->can(PermissionName::RefundsView->value);
    }
}
