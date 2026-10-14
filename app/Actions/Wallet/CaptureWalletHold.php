<?php

declare(strict_types=1);

namespace App\Actions\Wallet;

use App\Enums\WalletDirection;
use App\Enums\WalletHoldStatus;
use App\Enums\WalletTransactionStatus;
use App\Enums\WalletTransactionType;
use App\Exceptions\ForbiddenOperationException;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletHold;
use App\Models\WalletTransaction;
use App\Services\Wallet\PostWalletLedger;
use App\States\WalletHoldStateMachine;
use Illuminate\Support\Facades\DB;

final class CaptureWalletHold
{
    public function __construct(
        private readonly PostWalletLedger $ledger,
    ) {}

    public function __invoke(User $actor, WalletHold $hold, string $idempotencyKey): WalletTransaction
    {
        $hold->loadMissing('wallet');

        if ($hold->wallet->user_id !== $actor->id) {
            throw new ForbiddenOperationException('Wallet hold does not belong to the actor.');
        }

        return DB::transaction(function () use ($hold, $idempotencyKey): WalletTransaction {
            $existing = WalletTransaction::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing !== null) {
                return $existing;
            }

            $lockedHold = WalletHold::query()->whereKey($hold->id)->lockForUpdate()->firstOrFail();

            if ($lockedHold->status === WalletHoldStatus::Captured) {
                return WalletTransaction::query()
                    ->where('wallet_hold_id', $lockedHold->id)
                    ->where('type', WalletTransactionType::Payment->value)
                    ->firstOrFail();
            }

            WalletHoldStateMachine::assert($lockedHold->status, WalletHoldStatus::Captured);

            $wallet = Wallet::query()->whereKey($lockedHold->wallet_id)->lockForUpdate()->firstOrFail();
            $before = $wallet->available_balance_minor;

            $wallet->held_balance_minor -= $lockedHold->amount_minor;
            $wallet->save();

            $lockedHold->status = WalletHoldStatus::Captured;
            $lockedHold->captured_at = now();
            $lockedHold->save();

            $transaction = new WalletTransaction;
            $transaction->wallet_id = $wallet->id;
            $transaction->wallet_hold_id = $lockedHold->id;
            $transaction->type = WalletTransactionType::Payment->value;
            $transaction->status = WalletTransactionStatus::Posted;
            $transaction->direction = WalletDirection::Debit;
            $transaction->amount_minor = $lockedHold->amount_minor;
            $transaction->balance_before_minor = $before;
            $transaction->balance_after_minor = $wallet->available_balance_minor;
            $transaction->reference_type = $lockedHold->reference_type;
            $transaction->reference_id = $lockedHold->reference_id;
            $transaction->description = 'Capture wallet hold';
            $transaction->idempotency_key = $idempotencyKey;
            $transaction->save();

            ($this->ledger)(
                $transaction,
                $wallet,
                PostWalletLedger::USER_WALLET,
                PostWalletLedger::TRAVORA_REVENUE,
            );

            return $transaction;
        });
    }
}
