<?php

declare(strict_types=1);

namespace App\Integrations\Payments\DTOs;

use App\Domain\MoneyMinor;

final readonly class ChargeRequest
{
    public function __construct(
        public string $userId,
        public MoneyMinor $amount,
        public string $idempotencyKey,
        public string $description,
        public ?string $referenceType = null,
        public ?string $referenceId = null,
    ) {}
}
