<?php

declare(strict_types=1);

namespace App\Integrations\Payments\DTOs;

final readonly class PaymentResult
{
    public function __construct(
        public bool $success,
        public string $status,
        public string $transactionId,
        public int $amountMinor,
        public string $currency,
        public ?string $failureReason = null,
    ) {}
}
