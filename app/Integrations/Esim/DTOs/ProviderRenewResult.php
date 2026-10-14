<?php

declare(strict_types=1);

namespace App\Integrations\Esim\DTOs;

final readonly class ProviderRenewResult
{
    public function __construct(
        public string $externalReference,
        public string $status,
        public ?int $validityDaysAdded = null,
    ) {}
}
