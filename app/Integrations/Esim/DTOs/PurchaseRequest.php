<?php

declare(strict_types=1);

namespace App\Integrations\Esim\DTOs;

final readonly class PurchaseRequest
{
    public function __construct(
        public string $externalPlanId,
        public string $idempotencyKey,
        public int $quantity = 1,
    ) {}
}
