<?php

declare(strict_types=1);

namespace App\Enums;

enum InstallationStatus: string
{
    case NotInstalled = 'not_installed';
    case Installed = 'installed';
    case Failed = 'failed';
}
