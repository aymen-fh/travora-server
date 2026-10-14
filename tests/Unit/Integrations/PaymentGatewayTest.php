<?php

declare(strict_types=1);

use App\Domain\MoneyMinor;
use App\Integrations\Esim\Exceptions\ProviderNotImplementedException;
use App\Integrations\Payments\DTOs\ChargeRequest;
use App\Integrations\Payments\Manager\PaymentManager;

it('resolves the wallet payment port', function (): void {
    $user = customer('50.00');
    $gateway = app(PaymentManager::class)->driver('wallet');

    $result = $gateway->charge(new ChargeRequest(
        userId: $user->id,
        amount: MoneyMinor::of(1500, 'LYD'),
        idempotencyKey: 'pay-wallet-1',
        description: 'test charge',
    ));

    expect($result->success)->toBeTrue();
    expect($result->amountMinor)->toBe(1500);
    expect($user->wallets()->first()->refresh()->available_balance_minor)->toBe(3500);
});

it('keeps the template payment gateway unimplemented', function (): void {
    $gateway = app(PaymentManager::class)->driver('template');

    $gateway->charge(new ChargeRequest(
        userId: '00000000-0000-0000-0000-000000000000',
        amount: MoneyMinor::of(100, 'LYD'),
        idempotencyKey: 'pay-tpl',
        description: 'nope',
    ));
})->throws(ProviderNotImplementedException::class);
