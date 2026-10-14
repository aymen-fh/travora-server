<?php

declare(strict_types=1);

namespace App\Exceptions;

final class CatalogConflictException extends DomainException
{
    public function httpStatus(): int
    {
        return 409;
    }

    public function errorCode(): string
    {
        return 'CATALOG_CONFLICT';
    }
}
