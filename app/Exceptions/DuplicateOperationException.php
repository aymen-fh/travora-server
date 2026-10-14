<?php

declare(strict_types=1);

namespace App\Exceptions;

final class DuplicateOperationException extends DomainException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }

    public function httpStatus(): int
    {
        return 409;
    }

    public function errorCode(): string
    {
        return 'DUPLICATE_OPERATION';
    }
}
