<?php

declare(strict_types=1);

namespace App\Actions\Payments;

use App\Actions\Audit\RecordAuditLog;
use App\Actions\Wallet\CreditWallet;
use App\Actions\Wallet\DebitWallet;
use App\Domain\MoneyMinor;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Enums\WalletTransactionType;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentRefund;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Payments\RecordOrderSettlement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class RefundPayment
{
    public function __construct(
        private readonly CreditWallet $creditWallet,
        private readonly DebitWallet $debitWallet,
        private readonly RecordAuditLog $audit,
        private readonly RecordOrderSettlement $settlement,
    ) {}

    public function __invoke(User $admin, Payment $payment, int $amountMinor, string $reason, string $idempotencyKey, Request $request): PaymentRefund
    {
        $existing = PaymentRefund::query()->where('idempotency_key', $idempotencyKey)->first();
        if ($existing !== null) {
            return $existing;
        }

        return DB::transaction(function () use ($admin, $payment, $amountMinor, $reason, $idempotencyKey, $request): PaymentRefund {
            $locked = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
            $again = PaymentRefund::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($again !== null) {
                return $again;
            }

            if (! in_array($locked->status, [PaymentStatus::Paid, PaymentStatus::PartiallyRefunded], true)) {
                throw ValidationException::withMessages(['payment' => 'Only a paid payment can be refunded.']);
            }

            $locked->loadMissing('paymentMethod');
            $method = $locked->paymentMethod?->type;
            $isWallet = $method === 'wallet' || $method === PaymentMethod::Wallet;
            if (! $isWallet && $locked->type !== PaymentType::WalletTopup) {
                throw ValidationException::withMessages([
                    'payment' => 'Only wallet payments can be refunded. Card and gateway refunds need a provider driver.',
                ]);
            }

            $remaining = $locked->amount_minor - $locked->refunded_minor;
            if ($amountMinor < 1 || $amountMinor > $remaining) {
                throw ValidationException::withMessages(['amount_minor' => 'Refund amount exceeds the remaining balance.']);
            }

            $order = $locked->order_id === null
                ? null
                : Order::query()->whereKey($locked->order_id)->lockForUpdate()->first();
            $refundedBefore = $locked->refunded_minor;

            $refund = ($this->settlement)->refund(
                $locked,
                $order,
                $amountMinor,
                $locked->currency,
                $reason,
                $idempotencyKey,
            );

            $locked->loadMissing('paymentMethod', 'user');

            $this->moveWallet($locked, $amountMinor, $idempotencyKey);

            ($this->audit)(
                'payment.refunded',
                $admin,
                Payment::class,
                $locked->id,
                ['refunded_minor' => $refundedBefore],
                ['refund_id' => $refund->id, 'amount_minor' => $amountMinor, 'currency' => $locked->currency],
                $request->ip(),
                $request->userAgent(),
            );

            return $refund;
        });
    }

    private function moveWallet(Payment $payment, int $amountMinor, string $idempotencyKey): void
    {
        $method = $payment->paymentMethod?->type;
        $isWallet = $method === 'wallet' || $method === PaymentMethod::Wallet;
        if (! $isWallet && $payment->type !== PaymentType::WalletTopup) {
            return;
        }

        $owner = $payment->user;
        $wallet = $owner === null ? null : Wallet::query()
            ->where('user_id', $owner->id)
            ->where('currency', $payment->currency)
            ->first();

        if ($owner === null || $wallet === null) {
            throw ValidationException::withMessages(['payment' => 'The customer wallet for this payment was not found.']);
        }

        $money = MoneyMinor::of($amountMinor, $payment->currency);
        if ($payment->type === PaymentType::WalletTopup) {
            ($this->debitWallet)($owner, $wallet, $money, 'Admin refund of wallet top-up', $idempotencyKey, Payment::class, $payment->id);

            return;
        }

        ($this->creditWallet)(
            $owner,
            $wallet,
            $money,
            WalletTransactionType::Refund,
            'Admin refund',
            $idempotencyKey,
            Payment::class,
            $payment->id,
        );
    }
}
