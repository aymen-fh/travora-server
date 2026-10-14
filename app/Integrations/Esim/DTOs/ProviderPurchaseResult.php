<?php

declare(strict_types=1);

namespace App\Integrations\Esim\DTOs;

use App\Domain\EsimFulfillmentResult;

final readonly class ProviderPurchaseResult
{
    public function __construct(
        public string $externalOrderId,
        public string $status,
        public ?EsimFulfillmentResult $fulfillment = null,
    ) {}

    public function isCompleted(): bool
    {
        return $this->status === 'completed' && $this->fulfillment !== null && $this->fulfillment->externalProfileId !== '';
    }
}
