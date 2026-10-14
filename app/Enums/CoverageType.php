<?php

declare(strict_types=1);

namespace App\Enums;

enum CoverageType: string
{
    case Country = 'country';
    case MultiCountry = 'multi_country';
    case Regional = 'regional';
    case Global = 'global';
}
