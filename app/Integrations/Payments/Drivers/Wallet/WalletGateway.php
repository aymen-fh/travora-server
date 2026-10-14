<?php

declare(strict_types=1);

namespace App\Integrations\Payments\Drivers\Wallet;

use App\Actions\Wallet\CreditWallet;
use App\Actions\Wallet\DebitWallet;
use App\Domain\MoneyMinor;
use App\Enums\WalletTransactionStatus;
use App\Enums\WalletTransactionType;
use App\Integrations\Payments\Contracts\PaymentGateway;
use App\Integrations\Payments\DTOs\ChargeRequest;
use App\Integrations\Payments\DTOs\PaymentResult;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;

final class WalletGateway implements PaymentGateway
{
    public function __construct(
        private readonly DebitWallet $debitWallet,
        private readonly CreditWallet $creditWallet,
    ) {}

    public function name(): string
    {
        return 'wallet';
    }

    public function charge(ChargeRequest $request): PaymentResult
    {
        $user = User::query()->findOrFail($request->userId);
        $wallet = $this->wallet($user, $request->amount->currency);

        $transaction = ($this->debitWallet)(
            $user,
            $wallet,
            $request->amount,
            $request->description,
            $request->idempotencyKey,
            $request->referenceType,
            $request->referenceId,
        );

        return new PaymentResult(
            success: true,
            status: 'paid',
            transactionId: $transaction->id,
            amountMinor: $request->amount->amount,
            currency: $request->amount->currency,
        );
    }

    public function refund(string $transactionId, MoneyMinor $amount, string $idempotencyKey): PaymentResult
    {
        $original = WalletTransaction::query()->with('wallet.user')->findOrFail($transactionId);
        $wallet = $original->wallet;
        $user = $wallet->user;

        $credit = ($this->creditWallet)(
            $user,
            $wallet,
            $amount,
            WalletTransactionType::Refund,
            'Refund',
            $idempotencyKey,
            $original->reference_type,
            $original->reference_id,
        );

        return new PaymentResult(
            success: true,
            status: 'refunded',
            transactionId: $credit->id,
            amountMinor: $amount->amount,
            currency: $amount->currency,
        );
    }

    public function verify(string $transactionId): PaymentResult
    {
        $transaction = WalletTransaction::query()->with('wallet')->findOrFail($transactionId);

        return new PaymentResult(
            success: $transaction->status === WalletTransactionStatus::Posted,
            status: $transaction->status->value,
            transactionId: $transaction->id,
            amountMinor: $transaction->amount_minor,
            currency: $transaction->wallet->currency,
        );
    }

    private function wallet(User $user, string $currency): Wallet
    {
        return Wallet::query()
            ->where('user_id', $user->id)
            ->where('currency', $currency)
            ->firstOrFail();
    }
}
