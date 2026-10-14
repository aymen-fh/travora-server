<?php

declare(strict_types=1);

namespace App\Enums;

enum WalletTransactionType: string
{
    case Topup = 'topup';
    case Payment = 'payment';
    case Refund = 'refund';
    case Hold = 'hold';
    case Release = 'release';
    case Adjustment = 'adjustment';
}
