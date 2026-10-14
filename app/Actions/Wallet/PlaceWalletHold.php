<?php

declare(strict_types=1);

namespace App\Actions\Wallet;

use App\Domain\MoneyMinor;
use App\Domain\References\AssertReference;
use App\Enums\WalletDirection;
use App\Enums\WalletHoldStatus;
use App\Enums\WalletStatus;
use App\Enums\WalletTransactionStatus;
use App\Enums\WalletTransactionType;
use App\Exceptions\ForbiddenOperationException;
use App\Exceptions\InsufficientBalanceException;
use App\Exceptions\WalletNotOperableException;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletHold;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;

final class PlaceWalletHold
{
    public function __construct(
        private readonly AssertReference $references,
    ) {}

    public function __invoke(
        User $actor,
        Wallet $wallet,
        MoneyMinor $amount,
        string $idempotencyKey,
        ?string $referenceType = null,
        ?string $referenceId = null,
    ): WalletHold {
        if ($wallet->user_id !== $actor->id) {
            throw new ForbiddenOperationException('Wallet does not belong to the actor.');
        }

        if ($amount->isZero()) {
            throw new WalletNotOperableException('Hold amount must be positive.');
        }

        return DB::transaction(function () use ($wallet, $amount, $idempotencyKey, $referenceType, $referenceId): WalletHold {
            $existing = WalletHold::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing !== null) {
                return $existing;
            }

            $locked = Wallet::query()->whereKey($wallet->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== WalletStatus::Active) {
                throw new WalletNotOperableException('Wallet is not active.');
            }

            if ($locked->available_balance_minor < $amount->amount) {
                throw InsufficientBalanceException::for(
                    (string) $locked->available_balance_minor,
                    (string) $amount->amount,
                    $locked->currency,
                );
            }

            ($this->references)->wallet($referenceType, $referenceId);

            $before = $locked->available_balance_minor;
            $locked->available_balance_minor = $before - $amount->amount;
            $locked->held_balance_minor += $amount->amount;
            $locked->save();

            $hold = new WalletHold;
            $hold->wallet_id = $locked->id;
            $hold->amount_minor = $amount->amount;
            $hold->currency = $locked->currency;
            $hold->status = WalletHoldStatus::Active;
            $hold->reference_type = $referenceType;
            $hold->reference_id = $referenceId;
            $hold->idempotency_key = $idempotencyKey;
            $hold->save();

            $transaction = new WalletTransaction;
            $transaction->wallet_id = $locked->id;
            $transaction->wallet_hold_id = $hold->id;
            $transaction->type = WalletTransactionType::Hold->value;
            $transaction->status = WalletTransactionStatus::Posted;
            $transaction->direction = WalletDirection::Debit;
            $transaction->amount_minor = $amount->amount;
            $transaction->balance_before_minor = $before;
            $transaction->balance_after_minor = $locked->available_balance_minor;
            $transaction->reference_type = $referenceType;
            $transaction->reference_id = $referenceId;
            $transaction->description = 'Hold funds';
            $transaction->idempotency_key = $idempotencyKey.'-txn';
            $transaction->save();

            return $hold;
        });
    }
}
