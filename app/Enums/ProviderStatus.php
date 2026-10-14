<?php

declare(strict_types=1);

namespace App\Enums;

enum ProviderStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Degraded = 'degraded';
}
