<?php

declare(strict_types=1);

namespace App\Domain\Catalog;

use DateTimeInterface;
use InvalidArgumentException;

final readonly class PriceWindow
{
    public function __construct(
        public ?DateTimeInterface $startsAt,
        public ?DateTimeInterface $endsAt,
    ) {
        if ($this->startsAt !== null && $this->endsAt !== null && $this->endsAt <= $this->startsAt) {
            throw new InvalidArgumentException('Price window end must be after start.');
        }
    }

    public function contains(DateTimeInterface $at): bool
    {
        if ($this->startsAt !== null && $this->startsAt > $at) {
            return false;
        }

        if ($this->endsAt !== null && $this->endsAt <= $at) {
            return false;
        }

        return true;
    }
}
