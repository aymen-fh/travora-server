<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\Actions\Wallet\PlaceWalletHold;
use App\Domain\MoneyMinor;
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
use App\States\OrderStateMachine;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ReserveOrderWithWallet
{
    public function __construct(
        private readonly PlaceWalletHold $placeHold,
        private readonly RecordOrderStatus $history,
    ) {}

    public function __invoke(User $user, Order $order, Wallet $wallet): Order
    {
        if ($order->user_id !== $user->id) {
            throw new ForbiddenOperationException('Order does not belong to the actor.');
        }

        if ($wallet->currency !== $order->currency) {
            throw new ForbiddenOperationException('Wallet currency does not match the order.');
        }

        try {
            return DB::transaction(function () use ($user, $order, $wallet): Order {
                $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

                if ($locked->payment_status === OrderPaymentStatus::Paid) {
                    return $locked->fresh(['items', 'payments']) ?? $locked;
                }

                if (in_array($locked->status, [OrderStatus::Failed, OrderStatus::Cancelled, OrderStatus::Completed], true)) {
                    return $locked->fresh(['items', 'payments']) ?? $locked;
                }

                $existingPayment = Payment::query()
                    ->where('idempotency_key', $locked->idempotency_key.'-authorize')
                    ->lockForUpdate()
                    ->first();

                if ($existingPayment !== null) {
                    return $this->ensureReserved($locked, $user);
                }

                $amount = MoneyMinor::of($locked->total_minor, $locked->currency);

                ($this->placeHold)(
                    $user,
                    $wallet,
                    $amount,
                    $locked->idempotency_key.'-hold',
                    Order::class,
                    $locked->id,
                );

                $payment = new Payment;
                $payment->user_id = $user->id;
                $payment->order_id = $locked->id;
                $payment->payment_number = 'PAY-'.strtoupper(Str::random(10));
                $payment->type = PaymentType::Charge;
                $payment->status = PaymentStatus::Authorized;
                $payment->amount_minor = $locked->total_minor;
                $payment->currency = $locked->currency;
                $payment->idempotency_key = $locked->idempotency_key.'-authorize';
                $payment->authorized_at = now();
                $payment->save();

                return $this->ensureReserved($locked->fresh() ?? $locked, $user);
            });
        } catch (UniqueConstraintViolationException) {
            return $this->ensureReserved($order->fresh() ?? $order, $user);
        }
    }

    private function ensureReserved(Order $order, User $user): Order
    {
        if ($order->status !== OrderStatus::Pending) {
            return $order->fresh(['items', 'payments']) ?? $order;
        }

        OrderStateMachine::assert($order->status, OrderStatus::Confirmed);
        $fromStatus = $order->status;
        $order->status = OrderStatus::Confirmed;
        $order->save();

        ($this->history)($order, 'order', OrderStatus::Confirmed->value, $fromStatus->value, $user, 'wallet hold placed');

        return $order->fresh(['items', 'payments']) ?? $order;
    }
}
