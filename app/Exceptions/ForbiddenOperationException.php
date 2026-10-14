<?php

declare(strict_types=1);

namespace App\Exceptions;

final class ForbiddenOperationException extends DomainException
{
    public function httpStatus(): int
    {
        return 403;
    }

    public function errorCode(): string
    {
        return 'FORBIDDEN_OPERATION';
    }
}
