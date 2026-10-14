<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\Actions\Wallet\CaptureWalletHold;
use App\Actions\Wallet\PlaceWalletHold;
use App\Domain\MoneyMinor;
use App\Enums\FulfillmentStatus;
use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Exceptions\ForbiddenOperationException;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Orders\RecordOrderStatus;
use App\Services\Payments\RecordOrderSettlement;
use App\States\OrderStateMachine;
use App\States\PaymentStateMachine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class PayOrderWithWallet
{
    public function __construct(
        private readonly PlaceWalletHold $placeHold,
        private readonly CaptureWalletHold $captureHold,
        private readonly RecordOrderStatus $history,
        private readonly RecordOrderSettlement $settlement,
    ) {}

    public function __invoke(User $user, Order $order, Wallet $wallet): Order
    {
        if ($order->user_id !== $user->id) {
            throw new ForbiddenOperationException('Order does not belong to the actor.');
        }

        if ($wallet->currency !== $order->currency) {
            throw new ForbiddenOperationException('Wallet currency does not match the order.');
        }

        return DB::transaction(function () use ($user, $order, $wallet): Order {
            $order = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $existingPayment = Payment::query()->where('idempotency_key', $order->idempotency_key.'-pay')->lockForUpdate()->first();
            if ($existingPayment !== null) {
                return $this->ensureOrderPaid($order, $user);
            }

            OrderStateMachine::assert($order->status, OrderStatus::Confirmed);
            PaymentStateMachine::assert($order->payment_status, OrderPaymentStatus::Paid);

            $amount = MoneyMinor::of($order->total_minor, $order->currency);

            $hold = ($this->placeHold)(
                $user,
                $wallet,
                $amount,
                $order->idempotency_key.'-hold',
                Order::class,
                $order->id,
            );

            $transaction = ($this->captureHold)($user, $hold, $order->idempotency_key.'-capture');

            $payment = new Payment;
            $payment->user_id = $user->id;
            $payment->order_id = $order->id;
            $payment->payment_number = 'PAY-'.strtoupper(Str::random(10));
            $payment->type = PaymentType::Charge;
            $payment->status = PaymentStatus::Paid;
            $payment->amount_minor = $order->total_minor;
            $payment->currency = $order->currency;
            $payment->idempotency_key = $order->idempotency_key.'-pay';
            $payment->paid_at = now();
            $payment->save();

            $fromStatus = $order->status;
            $fromPayment = $order->payment_status;

            ($this->settlement)->allocate(
                $order,
                $payment,
                $order->total_minor,
                $order->currency,
                'wallet',
                $transaction->id,
            );

            $order->status = OrderStatus::Confirmed;
            $order->fulfillment_status = FulfillmentStatus::Pending;
            $order->save();

            ($this->history)($order, 'order', OrderStatus::Confirmed->value, $fromStatus->value, $user, 'wallet captured');
            ($this->history)($order, 'payment', OrderPaymentStatus::Paid->value, $fromPayment->value, $user);

            return $order->fresh(['items', 'payments']) ?? $order;
        });
    }

    private function ensureOrderPaid(Order $order, User $user): Order
    {
        if ($order->payment_status === OrderPaymentStatus::Paid && $order->status !== OrderStatus::Pending) {
            return $order->fresh(['items', 'payments']) ?? $order;
        }

        $fromStatus = $order->status;
        $fromPayment = $order->payment_status;

        ($this->settlement)->syncPaidFromAllocations($order);

        $order->status = OrderStatus::Confirmed;
        $order->fulfillment_status = FulfillmentStatus::Pending;
        $order->save();

        if ($fromStatus !== OrderStatus::Confirmed) {
            ($this->history)($order, 'order', OrderStatus::Confirmed->value, $fromStatus->value, $user, 'wallet captured');
        }

        if ($fromPayment !== OrderPaymentStatus::Paid) {
            ($this->history)($order, 'payment', OrderPaymentStatus::Paid->value, $fromPayment->value, $user);
        }

        return $order->fresh(['items', 'payments']) ?? $order;
    }
}
