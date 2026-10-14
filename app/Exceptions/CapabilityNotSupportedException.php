<?php

declare(strict_types=1);

namespace App\Exceptions;

final class CapabilityNotSupportedException extends DomainException
{
    public function __construct(
        public readonly string $provider,
        public readonly string $capability,
    ) {
        parent::__construct("Provider [{$provider}] does not support [{$capability}].");
    }

    public function httpStatus(): int
    {
        return 422;
    }

    public function errorCode(): string
    {
        return 'CAPABILITY_NOT_SUPPORTED';
    }

    public function context(): array
    {
        return [
            'provider' => $this->provider,
            'capability' => $this->capability,
        ];
    }
}
