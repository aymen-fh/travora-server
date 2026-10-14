<?php

declare(strict_types=1);

namespace App\Enums;

enum WalletTransactionStatus: string
{
    case Pending = 'pending';
    case Posted = 'posted';
    case Reversed = 'reversed';
    case Failed = 'failed';
}
