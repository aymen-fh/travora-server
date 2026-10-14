<?php

declare(strict_types=1);

namespace App\Exceptions;

final class CatalogUnavailableException extends DomainException
{
    public function httpStatus(): int
    {
        return 422;
    }

    public function errorCode(): string
    {
        return 'CATALOG_UNAVAILABLE';
    }
}
