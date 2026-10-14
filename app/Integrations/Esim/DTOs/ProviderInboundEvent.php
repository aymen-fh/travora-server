<?php

declare(strict_types=1);

namespace App\Integrations\Esim\DTOs;

use App\Domain\EsimUsageSnapshot;

final readonly class ProviderInboundEvent
{
    public function __construct(
        public string $type,
        public ?string $externalProfileId = null,
        public ?EsimUsageSnapshot $usage = null,
    ) {}
}
