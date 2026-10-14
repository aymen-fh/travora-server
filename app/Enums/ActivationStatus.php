<?php

declare(strict_types=1);

namespace App\Enums;

enum ActivationStatus: string
{
    case NotActivated = 'not_activated';
    case Activated = 'activated';
    case Failed = 'failed';
}
