<?php

declare(strict_types=1);

use App\Actions\Wallet\CaptureWalletHold;
use App\Actions\Wallet\CreditWallet;
use App\Actions\Wallet\DebitWallet;
use App\Actions\Wallet\PlaceWalletHold;
use App\Actions\Wallet\ReleaseWalletHold;
use App\Domain\MoneyMinor;
use App\Enums\WalletHoldStatus;
use App\Enums\WalletTransactionType;
use App\Exceptions\ForbiddenOperationException;
use App\Exceptions\InsufficientBalanceException;
use App\Models\WalletLedgerEntry;
use App\Models\WalletTransaction;

it('credits and debits a wallet in minor units', function (): void {
    $user = customer('50.00');
    $wallet = $user->wallets()->firstOrFail();

    app(CreditWallet::class)(
        $user,
        $wallet,
        MoneyMinor::of(2500, 'LYD'),
        WalletTransactionType::Topup,
        'bonus',
        'credit-1',
    );

    $wallet->refresh();
    expect($wallet->available_balance_minor)->toBe(7500);

    app(DebitWallet::class)($user, $wallet->refresh(), MoneyMinor::of(1000, 'LYD'), 'purchase', 'debit-1');
    $wallet->refresh();

    expect($wallet->available_balance_minor)->toBe(6500);
    expect($wallet->transactions()->count())->toBe(2);
});

it('never allows a negative wallet balance', function (): void {
    $user = customer('5.00');
    $wallet = $user->wallets()->firstOrFail();

    app(DebitWallet::class)($user, $wallet, MoneyMinor::of(1000, 'LYD'), 'purchase', 'debit-overdraw');
})->throws(InsufficientBalanceException::class);

it('reuses a wallet debit when the idempotency key matches', function (): void {
    $user = customer('50.00');
    $wallet = $user->wallets()->firstOrFail();
    $debit = app(DebitWallet::class);

    $first = $debit($user, $wallet, MoneyMinor::of(1000, 'LYD'), 'purchase', 'debit-idem');
    $second = $debit($user, $wallet, MoneyMinor::of(1000, 'LYD'), 'purchase', 'debit-idem');

    expect($first->id)->toBe($second->id);
    expect($wallet->refresh()->available_balance_minor)->toBe(4000);
});

it('places, captures, and posts a balanced ledger', function (): void {
    $user = customer('80.00');
    $wallet = $user->wallets()->firstOrFail();

    $hold = app(PlaceWalletHold::class)($user, $wallet, MoneyMinor::of(3000, 'LYD'), 'hold-1');
    $wallet->refresh();

    expect($hold->status)->toBe(WalletHoldStatus::Active);
    expect($wallet->available_balance_minor)->toBe(5000);
    expect($wallet->held_balance_minor)->toBe(3000);

    app(CaptureWalletHold::class)($user, $hold, 'capture-1');
    $wallet->refresh();

    expect($hold->refresh()->status)->toBe(WalletHoldStatus::Captured);
    expect($wallet->available_balance_minor)->toBe(5000);
    expect($wallet->held_balance_minor)->toBe(0);

    $transaction = WalletTransaction::query()->where('idempotency_key', 'capture-1')->firstOrFail();
    $debit = (int) WalletLedgerEntry::query()->where('wallet_transaction_id', $transaction->id)->sum('debit_minor');
    $credit = (int) WalletLedgerEntry::query()->where('wallet_transaction_id', $transaction->id)->sum('credit_minor');

    expect($debit)->toBe(3000);
    expect($credit)->toBe(3000);
});

it('releases a hold back to available balance', function (): void {
    $user = customer('40.00');
    $wallet = $user->wallets()->firstOrFail();

    $hold = app(PlaceWalletHold::class)($user, $wallet, MoneyMinor::of(1500, 'LYD'), 'hold-release');
    app(ReleaseWalletHold::class)($user, $hold, 'release-1');

    expect($hold->refresh()->status)->toBe(WalletHoldStatus::Released);
    expect($wallet->refresh()->available_balance_minor)->toBe(4000);
    expect($wallet->held_balance_minor)->toBe(0);
});

it('rejects a hold on another user wallet', function (): void {
    $owner = customer('20.00');
    $stranger = customer('20.00');
    $wallet = $owner->wallets()->firstOrFail();

    app(PlaceWalletHold::class)($stranger, $wallet, MoneyMinor::of(100, 'LYD'), 'hold-stolen');
})->throws(ForbiddenOperationException::class);
