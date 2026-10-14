<?php

declare(strict_types=1);

namespace App\Integrations\Esim\DTOs;

final readonly class ProviderCoverageDTO
{
    public function __construct(
        public string $iso2,
        public string $name,
        public ?string $iso3 = null,
    ) {}
}
