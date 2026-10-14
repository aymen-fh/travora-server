<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\Actions\Wallet\CaptureWalletHold;
use App\Enums\OrderPaymentStatus;
use App\Enums\PaymentStatus;
use App\Enums\WalletHoldStatus;
use App\Exceptions\ForbiddenOperationException;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\User;
use App\Models\WalletHold;
use App\Services\Orders\RecordOrderStatus;
use App\Services\Payments\RecordOrderSettlement;
use Illuminate\Support\Facades\DB;

final class CaptureReservedOrderPayment
{
    public function __construct(
        private readonly CaptureWalletHold $captureHold,
        private readonly RecordOrderStatus $history,
        private readonly RecordOrderSettlement $settlement,
    ) {}

    public function __invoke(User $user, Order $order): Order
    {
        if ($order->user_id !== $user->id) {
            throw new ForbiddenOperationException('Order does not belong to the actor.');
        }

        return DB::transaction(function () use ($user, $order): Order {
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if (in_array($locked->payment_status, [
                OrderPaymentStatus::Paid,
                OrderPaymentStatus::Failed,
                OrderPaymentStatus::Refunded,
                OrderPaymentStatus::PartiallyRefunded,
            ], true)) {
                return $locked->fresh(['items', 'payments']) ?? $locked;
            }

            $hold = WalletHold::query()
                ->where('reference_type', Order::class)
                ->where('reference_id', $locked->id)
                ->whereIn('status', [WalletHoldStatus::Active, WalletHoldStatus::Captured])
                ->lockForUpdate()
                ->first();

            if ($hold === null) {
                throw new ForbiddenOperationException('Order has no reserved wallet hold to capture.');
            }

            $transaction = ($this->captureHold)($user, $hold, $locked->idempotency_key.'-capture');

            $payment = Payment::query()
                ->where('order_id', $locked->id)
                ->whereIn('idempotency_key', [
                    $locked->idempotency_key.'-authorize',
                    $locked->idempotency_key.'-pay',
                ])
                ->lockForUpdate()
                ->first();

            if ($payment === null) {
                throw new ForbiddenOperationException('Order has no reserved payment to capture.');
            }

            if ($payment->status !== PaymentStatus::Paid) {
                $payment->status = PaymentStatus::Paid;
                $payment->paid_at = now();
                $payment->save();
            }

            $allocation = PaymentAllocation::query()
                ->where('order_id', $locked->id)
                ->where('payment_id', $payment->id)
                ->first();

            $fromPayment = $locked->payment_status;

            if ($allocation === null) {
                ($this->settlement)->allocate(
                    $locked,
                    $payment,
                    $locked->total_minor,
                    $locked->currency,
                    'wallet',
                    $transaction->id,
                );
            } else {
                ($this->settlement)->syncPaidFromAllocations($locked);
            }

            if ($fromPayment !== OrderPaymentStatus::Paid) {
                ($this->history)($locked, 'payment', OrderPaymentStatus::Paid->value, $fromPayment->value, $user, 'wallet hold captured');
            }

            return $locked->fresh(['items', 'payments']) ?? $locked;
        });
    }
}
