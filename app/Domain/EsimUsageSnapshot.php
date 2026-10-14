<?php

declare(strict_types=1);

namespace App\Domain;

use InvalidArgumentException;

final readonly class EsimUsageSnapshot
{
    public function __construct(
        public int $usedMb,
        public ?int $remainingMb = null,
        public string $source = 'provider',
    ) {
        if ($usedMb < 0) {
            throw new InvalidArgumentException('Used megabytes cannot be negative.');
        }

        if ($remainingMb !== null && $remainingMb < 0) {
            throw new InvalidArgumentException('Remaining megabytes cannot be negative.');
        }
    }
}
