<?php

declare(strict_types=1);

namespace App\Integrations\Esim\DTOs;

final readonly class ProviderErrorDTO
{
    public function __construct(
        public string $code,
        public string $message,
        public bool $retryable = false,
    ) {}
}
