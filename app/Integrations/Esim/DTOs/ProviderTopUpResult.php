<?php

declare(strict_types=1);

namespace App\Integrations\Esim\DTOs;

final readonly class ProviderTopUpResult
{
    public function __construct(
        public string $externalReference,
        public string $status,
        public ?int $dataMbAdded = null,
    ) {}
}
