<?php

declare(strict_types=1);

use App\Domain\Catalog\PriceWindow;

it('accepts an open-ended price window', function (): void {
    $window = new PriceWindow(now()->subDay(), null);

    expect($window->contains(now()))->toBeTrue();
});

it('rejects a window whose end is not after its start', function (): void {
    $at = now();
    new PriceWindow($at, $at);
})->throws(InvalidArgumentException::class);
