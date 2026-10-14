<?php

declare(strict_types=1);

namespace App\Enums;

enum EsimProfileStatus: string
{
    case Purchased = 'purchased';
    case Provisioning = 'provisioning';
    case Ready = 'ready';
    case Installed = 'installed';
    case Activated = 'activated';
    case Suspended = 'suspended';
    case Expired = 'expired';
    case Cancelled = 'cancelled';
    case Failed = 'failed';
}
