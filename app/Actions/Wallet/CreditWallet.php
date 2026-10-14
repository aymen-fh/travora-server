<?php

declare(strict_types=1);

namespace App\Actions\Wallet;

use App\Domain\MoneyMinor;
use App\Domain\References\AssertReference;
use App\Enums\WalletDirection;
use App\Enums\WalletStatus;
use App\Enums\WalletTransactionStatus;
use App\Enums\WalletTransactionType;
use App\Exceptions\ForbiddenOperationException;
use App\Exceptions\WalletNotOperableException;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\Wallet\PostWalletLedger;
use Illuminate\Support\Facades\DB;

final class CreditWallet
{
    public function __construct(
        private readonly PostWalletLedger $ledger,
        private readonly AssertReference $references,
    ) {}

    public function __invoke(
        User $actor,
        Wallet $wallet,
        MoneyMinor $amount,
        WalletTransactionType $type,
        string $description,
        string $idempotencyKey,
        ?string $referenceType = null,
        ?string $referenceId = null,
    ): WalletTransaction {
        if ($wallet->user_id !== $actor->id && $type !== WalletTransactionType::Adjustment) {
            throw new ForbiddenOperationException('Wallet does not belong to the actor.');
        }

        if ($amount->isZero()) {
            throw new WalletNotOperableException('Credit amount must be positive.');
        }

        $creditType = in_array($type, [WalletTransactionType::Refund, WalletTransactionType::Topup, WalletTransactionType::Adjustment], true)
            ? $type
            : WalletTransactionType::Topup;

        return DB::transaction(function () use ($wallet, $amount, $creditType, $description, $idempotencyKey, $referenceType, $referenceId): WalletTransaction {
            $existing = WalletTransaction::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing !== null) {
                return $existing;
            }

            $locked = Wallet::query()->whereKey($wallet->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== WalletStatus::Active && $creditType !== WalletTransactionType::Adjustment) {
                throw new WalletNotOperableException('Wallet is not active.');
            }

            ($this->references)->wallet($referenceType, $referenceId);

            $before = $locked->available_balance_minor;
            $after = $before + $amount->amount;

            $transaction = new WalletTransaction;
            $transaction->wallet_id = $locked->id;
            $transaction->type = $creditType->value;
            $transaction->status = WalletTransactionStatus::Posted;
            $transaction->direction = WalletDirection::Credit;
            $transaction->amount_minor = $amount->amount;
            $transaction->balance_before_minor = $before;
            $transaction->balance_after_minor = $after;
            $transaction->reference_type = $referenceType;
            $transaction->reference_id = $referenceId;
            $transaction->description = $description;
            $transaction->idempotency_key = $idempotencyKey;
            $transaction->save();

            $debitCode = $creditType === WalletTransactionType::Refund
                ? PostWalletLedger::REFUNDS
                : PostWalletLedger::PAYMENT_PROCESSING;

            ($this->ledger)($transaction, $locked, $debitCode, PostWalletLedger::USER_WALLET);

            $locked->available_balance_minor = $after;
            $locked->save();

            return $transaction;
        });
    }
}
