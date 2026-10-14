<?php

declare(strict_types=1);

namespace App\Integrations\Esim\DTOs;

final readonly class RenewRequest
{
    public function __construct(
        public string $externalProfileId,
        public string $externalPlanId,
        public string $idempotencyKey,
    ) {}
}
