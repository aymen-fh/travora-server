<?php

declare(strict_types=1);

namespace App\Services\Wallet;

use App\Models\Wallet;
use App\Models\WalletAccount;
use App\Models\WalletLedgerEntry;
use App\Models\WalletTransaction;
use Illuminate\Database\UniqueConstraintViolationException;
use LogicException;

final class PostWalletLedger
{
    public const USER_WALLET = 'USER_WALLET';

    public const TRAVORA_REVENUE = 'TRAVORA_REVENUE';

    public const PAYMENT_PROCESSING = 'PAYMENT_PROCESSING';

    public const REFUNDS = 'REFUNDS';

    public function __invoke(
        WalletTransaction $transaction,
        Wallet $wallet,
        string $debitCode,
        string $creditCode,
    ): void {
        $debit = $this->account($wallet, $debitCode);
        $credit = $this->account($wallet, $creditCode);

        $debitEntry = new WalletLedgerEntry;
        $debitEntry->wallet_transaction_id = $transaction->id;
        $debitEntry->wallet_account_id = $debit->id;
        $debitEntry->debit_minor = $transaction->amount_minor;
        $debitEntry->credit_minor = 0;
        $debitEntry->save();

        $creditEntry = new WalletLedgerEntry;
        $creditEntry->wallet_transaction_id = $transaction->id;
        $creditEntry->wallet_account_id = $credit->id;
        $creditEntry->debit_minor = 0;
        $creditEntry->credit_minor = $transaction->amount_minor;
        $creditEntry->save();

        $sumDebit = (int) $transaction->ledgerEntries()->sum('debit_minor');
        $sumCredit = (int) $transaction->ledgerEntries()->sum('credit_minor');

        if ($sumDebit !== $sumCredit) {
            throw new LogicException('Wallet ledger entries must balance.');
        }
    }

    public function account(Wallet $wallet, string $code): WalletAccount
    {
        $isSystem = $code !== self::USER_WALLET;

        $account = WalletAccount::query()
            ->where('code', $code)
            ->where('currency', $wallet->currency)
            ->when(
                $isSystem,
                fn ($query) => $query->whereNull('wallet_id'),
                fn ($query) => $query->where('wallet_id', $wallet->id),
            )
            ->first();

        if ($account !== null) {
            return $account;
        }

        $account = new WalletAccount;
        $account->wallet_id = $isSystem ? null : $wallet->id;
        $account->code = $code;
        $account->name = $code;
        $account->currency = $wallet->currency;
        $account->is_system = $isSystem;

        try {
            $account->save();
        } catch (UniqueConstraintViolationException $exception) {
            if (! $isSystem) {
                throw $exception;
            }

            return WalletAccount::query()
                ->whereNull('wallet_id')
                ->where('code', $code)
                ->where('currency', $wallet->currency)
                ->firstOrFail();
        }

        return $account;
    }
}
