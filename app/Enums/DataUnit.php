<?php

declare(strict_types=1);

namespace App\Enums;

enum DataUnit: string
{
    case Mb = 'MB';
    case Gb = 'GB';
    case Tb = 'TB';
}
