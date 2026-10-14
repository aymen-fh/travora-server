<?php

declare(strict_types=1);

use App\Domain\MoneyMinor;
use App\Domain\OrderTotals;

it('rejects negative money amounts', function (): void {
    new MoneyMinor(-1, 'LYD');
})->throws(InvalidArgumentException::class);

it('adds and subtracts minor units in the same currency', function (): void {
    $left = MoneyMinor::of(1000, 'LYD');
    $right = MoneyMinor::of(250, 'LYD');

    expect($left->add($right)->amount)->toBe(1250);
    expect($left->subtract($right)->amount)->toBe(750);
});

it('refuses mixed-currency arithmetic', function (): void {
    MoneyMinor::of(100, 'LYD')->add(MoneyMinor::of(100, 'USD'));
})->throws(InvalidArgumentException::class);

it('computes order totals without floats', function (): void {
    $totals = new OrderTotals(10000, 1500, 500, 'LYD');

    expect($totals->totalMinor())->toBe(9000);
});

it('rejects a discount larger than the subtotal', function (): void {
    new OrderTotals(1000, 1001, 0, 'LYD');
})->throws(InvalidArgumentException::class);
