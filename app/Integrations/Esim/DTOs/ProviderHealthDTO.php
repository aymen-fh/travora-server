<?php

declare(strict_types=1);

namespace App\Integrations\Esim\DTOs;

use App\Enums\HealthStatus;

final readonly class ProviderHealthDTO
{
    public function __construct(
        public HealthStatus $status,
        public int $latencyMs,
        public ?string $message = null,
    ) {}
}
