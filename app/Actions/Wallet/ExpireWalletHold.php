<?php

declare(strict_types=1);

namespace App\Actions\Wallet;

use App\Enums\WalletDirection;
use App\Enums\WalletHoldStatus;
use App\Enums\WalletTransactionStatus;
use App\Enums\WalletTransactionType;
use App\Models\Wallet;
use App\Models\WalletHold;
use App\Models\WalletTransaction;
use App\States\WalletHoldStateMachine;
use Illuminate\Support\Facades\DB;

final class ExpireWalletHold
{
    public function __invoke(WalletHold $hold, string $idempotencyKey): WalletHold
    {
        return DB::transaction(function () use ($hold, $idempotencyKey): WalletHold {
            $existing = WalletTransaction::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing !== null) {
                return WalletHold::query()->findOrFail($existing->wallet_hold_id);
            }

            $lockedHold = WalletHold::query()->whereKey($hold->id)->lockForUpdate()->firstOrFail();

            if (in_array($lockedHold->status, [WalletHoldStatus::Expired, WalletHoldStatus::Released, WalletHoldStatus::Captured], true)) {
                return $lockedHold;
            }

            WalletHoldStateMachine::assert($lockedHold->status, WalletHoldStatus::Expired);

            $wallet = Wallet::query()->whereKey($lockedHold->wallet_id)->lockForUpdate()->firstOrFail();
            $before = $wallet->available_balance_minor;

            $wallet->available_balance_minor += $lockedHold->amount_minor;
            $wallet->held_balance_minor -= $lockedHold->amount_minor;
            $wallet->save();

            $lockedHold->status = WalletHoldStatus::Expired;
            $lockedHold->released_at = now();
            $lockedHold->save();

            $transaction = new WalletTransaction;
            $transaction->wallet_id = $wallet->id;
            $transaction->wallet_hold_id = $lockedHold->id;
            $transaction->type = WalletTransactionType::Release->value;
            $transaction->status = WalletTransactionStatus::Posted;
            $transaction->direction = WalletDirection::Credit;
            $transaction->amount_minor = $lockedHold->amount_minor;
            $transaction->balance_before_minor = $before;
            $transaction->balance_after_minor = $wallet->available_balance_minor;
            $transaction->reference_type = $lockedHold->reference_type;
            $transaction->reference_id = $lockedHold->reference_id;
            $transaction->description = 'Expire wallet hold';
            $transaction->idempotency_key = $idempotencyKey;
            $transaction->save();

            return $lockedHold;
        });
    }
}
