<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentMethod: string
{
    case Wallet = 'wallet';
    case Card = 'card';
    case Gateway = 'gateway';
}
