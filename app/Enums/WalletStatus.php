<?php

declare(strict_types=1);

namespace App\Enums;

enum WalletStatus: string
{
    case Active = 'active';
    case Locked = 'locked';
    case Closed = 'closed';
}
