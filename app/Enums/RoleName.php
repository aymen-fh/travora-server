<?php

declare(strict_types=1);

namespace App\Enums;

enum RoleName: string
{
    case User = 'user';
    case Support = 'support';
    case Operator = 'operator';
    case Finance = 'finance';
    case Admin = 'admin';
    case SuperAdmin = 'super_admin';
}
