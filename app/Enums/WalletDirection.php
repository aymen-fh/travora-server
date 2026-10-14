<?php

declare(strict_types=1);

namespace App\Enums;

enum WalletDirection: string
{
    case Credit = 'credit';
    case Debit = 'debit';
}
