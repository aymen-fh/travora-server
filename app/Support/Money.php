<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

/**
 * Decimal money as a string. Never use floats for currency.
 */
final readonly class Money
{
    public function __construct(
        public string $amount,
        public string $currency,
    ) {
        if (! is_numeric($amount)) {
            throw new InvalidArgumentException('Money amount must be numeric.');
        }
    }

    public static function of(string|int|float $amount, string $currency): self
    {
        return new self(self::normalize((string) $amount), strtoupper($currency));
    }

    public static function zero(string $currency): self
    {
        return new self('0.000000', strtoupper($currency));
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self(self::normalize(bcadd($this->amount, $other->amount, 6)), $this->currency);
    }

    public function subtract(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self(self::normalize(bcsub($this->amount, $other->amount, 6)), $this->currency);
    }

    public function multiply(string $factor): self
    {
        return new self(self::normalize(bcmul($this->amount, $factor, 6)), $this->currency);
    }

    public function percentage(string $percent): self
    {
        return $this->multiply(bcdiv($percent, '100', 8));
    }

    public function greaterThan(self $other): bool
    {
        $this->assertSameCurrency($other);

        return bccomp($this->amount, $other->amount, 6) === 1;
    }

    public function lessThan(self $other): bool
    {
        $this->assertSameCurrency($other);

        return bccomp($this->amount, $other->amount, 6) === -1;
    }

    public function isNegative(): bool
    {
        return bccomp($this->amount, '0', 6) === -1;
    }

    public function isZero(): bool
    {
        return bccomp($this->amount, '0', 6) === 0;
    }

    public function format(): string
    {
        return number_format((float) $this->amount, 2, '.', '');
    }

    /**
     * Display helpers may round to 2 places. Storage stays at 6.
     *
     * @return array{amount: string, currency: string}
     */
    public function toDisplayArray(): array
    {
        return [
            'amount' => $this->format(),
            'currency' => $this->currency,
        ];
    }

    public static function normalize(string $amount): string
    {
        return bcadd($amount, '0', 6);
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException("Currency mismatch: {$this->currency} vs {$other->currency}.");
        }
    }
}
