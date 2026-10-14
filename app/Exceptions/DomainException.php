<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

abstract class DomainException extends RuntimeException
{
    abstract public function httpStatus(): int;

    abstract public function errorCode(): string;

    /**
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return [];
    }
}
