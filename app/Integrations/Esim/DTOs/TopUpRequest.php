<?php

declare(strict_types=1);

namespace App\Integrations\Esim\DTOs;

final readonly class TopUpRequest
{
    public function __construct(
        public string $externalProfileId,
        public string $externalPlanId,
        public string $idempotencyKey,
    ) {}
}
