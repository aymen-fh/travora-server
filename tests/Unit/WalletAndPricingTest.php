<?php

declare(strict_types=1);

use App\Actions\Wallet\CreditWallet;
use App\Actions\Wallet\DebitWallet;
use App\Domain\MoneyMinor;
use App\Enums\WalletTransactionType;
use App\Exceptions\InsufficientBalanceException;

it('adds markup without using floats', function (): void {
    $cost = MoneyMinor::of(1200, 'USD');
    $markup = intdiv($cost->amount * 40, 100);
    $price = $cost->add(MoneyMinor::of($markup, 'USD'));

    expect($price->amount)->toBe(1680);
});

it('credits and debits a wallet atomically', function (): void {
    $user = customer('50.00');
    $wallet = $user->wallets()->firstOrFail();

    app(CreditWallet::class)(
        $user,
        $wallet,
        MoneyMinor::of(2500, 'LYD'),
        WalletTransactionType::Topup,
        'bonus',
        'wallet-pricing-credit-1',
    );
    $wallet->refresh();
    expect($wallet->available_balance_minor)->toBe(7500);

    app(DebitWallet::class)($user, $wallet->refresh(), MoneyMinor::of(1000, 'LYD'), 'purchase', 'wallet-pricing-debit-1');
    $wallet->refresh();

    expect($wallet->available_balance_minor)->toBe(6500);
    expect($wallet->transactions()->count())->toBe(2);
});

it('never allows a negative wallet balance', function (): void {
    $user = customer('5.00');
    $wallet = $user->wallets()->firstOrFail();

    app(DebitWallet::class)($user, $wallet, MoneyMinor::of(1000, 'LYD'), 'purchase', 'wallet-pricing-overdraw');
})->throws(InsufficientBalanceException::class);

it('reuses a wallet debit when the idempotency key matches', function (): void {
    $user = customer('50.00');
    $wallet = $user->wallets()->firstOrFail();
    $debit = app(DebitWallet::class);

    $first = $debit($user, $wallet, MoneyMinor::of(1000, 'LYD'), 'purchase', 'wallet-pricing-idem');
    $second = $debit($user, $wallet, MoneyMinor::of(1000, 'LYD'), 'purchase', 'wallet-pricing-idem');

    expect($first->id)->toBe($second->id);
    expect($wallet->refresh()->available_balance_minor)->toBe(4000);
});
