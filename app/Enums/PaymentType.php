<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentType: string
{
    case Charge = 'charge';
    case Refund = 'refund';
    case WalletTopup = 'wallet_topup';
}
