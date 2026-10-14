<?php

declare(strict_types=1);

namespace App\Actions\Wallet;

use App\Actions\Audit\RecordAuditLog;
use App\Domain\MoneyMinor;
use App\Enums\WalletDirection;
use App\Enums\WalletTransactionType;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Http\Request;
use InvalidArgumentException;

final class AdjustWallet
{
    public function __construct(
        private readonly CreditWallet $credit,
        private readonly DebitWallet $debit,
        private readonly RecordAuditLog $audit,
    ) {}

    public function __invoke(
        User $actor,
        Wallet $wallet,
        WalletDirection $direction,
        MoneyMinor $amount,
        string $reason,
        string $idempotencyKey,
        ?Request $request = null,
    ): WalletTransaction {
        $wallet->loadMissing('user');

        $transaction = match ($direction) {
            WalletDirection::Credit => ($this->credit)(
                $actor,
                $wallet,
                $amount,
                WalletTransactionType::Adjustment,
                $reason,
                $idempotencyKey,
                User::class,
                $actor->id,
            ),
            WalletDirection::Debit => ($this->debit)(
                $wallet->user ?? throw new InvalidArgumentException('Wallet owner is missing.'),
                $wallet,
                $amount,
                $reason,
                $idempotencyKey,
                User::class,
                $actor->id,
            ),
        };

        ($this->audit)(
            'wallet.adjusted',
            $actor,
            Wallet::class,
            $wallet->id,
            null,
            [
                'direction' => $direction->value,
                'amount_minor' => $amount->amount,
                'currency' => $amount->currency,
                'reason' => $reason,
                'idempotency_key' => $idempotencyKey,
                'transaction_id' => $transaction->id,
            ],
            $request?->ip(),
            $request?->userAgent(),
        );

        return $transaction;
    }
}
