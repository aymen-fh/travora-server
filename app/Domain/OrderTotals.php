<?php

declare(strict_types=1);

namespace App\Domain;

use InvalidArgumentException;

final readonly class OrderTotals
{
    public function __construct(
        public int $subtotalMinor,
        public int $discountMinor,
        public int $taxMinor,
        public string $currency,
    ) {
        if ($subtotalMinor < 0 || $discountMinor < 0 || $taxMinor < 0) {
            throw new InvalidArgumentException('Order money fields cannot be negative.');
        }

        if ($discountMinor > $subtotalMinor) {
            throw new InvalidArgumentException('Discount cannot exceed subtotal.');
        }
    }

    public function totalMinor(): int
    {
        return $this->subtotalMinor - $this->discountMinor + $this->taxMinor;
    }
}
