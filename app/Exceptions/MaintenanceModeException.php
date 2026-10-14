<?php

declare(strict_types=1);

namespace App\Exceptions;

final class MaintenanceModeException extends DomainException
{
    public function httpStatus(): int
    {
        return 503;
    }

    public function errorCode(): string
    {
        return 'MAINTENANCE_MODE';
    }
}
