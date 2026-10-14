<?php

declare(strict_types=1);

namespace App\Exceptions;

final class InsufficientBalanceException extends DomainException
{
    public function __construct(
        public readonly string $available,
        public readonly string $required,
        public readonly string $currency,
    ) {
        parent::__construct('Insufficient wallet balance.');
    }

    public static function for(string $available, string $required, string $currency): self
    {
        return new self($available, $required, $currency);
    }

    public function httpStatus(): int
    {
        return 422;
    }

    public function errorCode(): string
    {
        return 'INSUFFICIENT_BALANCE';
    }

    public function context(): array
    {
        return [
            'available' => $this->available,
            'required' => $this->required,
            'currency' => $this->currency,
        ];
    }
}
