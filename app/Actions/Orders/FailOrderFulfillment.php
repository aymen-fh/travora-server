<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\Actions\Wallet\ReleaseWalletHold;
use App\Enums\EsimProfileStatus;
use App\Enums\FulfillmentStatus;
use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\WalletHoldStatus;
use App\Events\OrderFailed;
use App\Models\Order;
use App\Models\Payment;
use App\Models\WalletHold;
use App\Services\Orders\RecordOrderStatus;
use App\States\EsimStateMachine;
use App\States\FulfillmentStateMachine;
use App\States\OrderStateMachine;
use App\States\PaymentStateMachine;
use Illuminate\Support\Facades\DB;

final class FailOrderFulfillment
{
    public function __construct(
        private readonly ReleaseWalletHold $releaseHold,
        private readonly RecordOrderStatus $history,
    ) {}

    public function __invoke(Order $order, string $reason): Order
    {
        if ($order->status === OrderStatus::Failed) {
            return $order;
        }

        $order = DB::transaction(function () use ($order, $reason): Order {
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $locked->loadMissing(['items.esimProfile', 'user']);

            if ($locked->status === OrderStatus::Failed) {
                return $locked;
            }

            OrderStateMachine::assert($locked->status, OrderStatus::Failed);
            FulfillmentStateMachine::assert($locked->fulfillment_status, FulfillmentStatus::Failed);

            $from = $locked->status;
            $fromFulfillment = $locked->fulfillment_status;
            $fromPayment = $locked->payment_status;
            $locked->status = OrderStatus::Failed;
            $locked->fulfillment_status = FulfillmentStatus::Failed;

            $activeHold = WalletHold::query()
                ->where('reference_type', Order::class)
                ->where('reference_id', $locked->id)
                ->where('status', WalletHoldStatus::Active)
                ->lockForUpdate()
                ->first();

            if ($activeHold !== null && $locked->user !== null) {
                ($this->releaseHold)($locked->user, $activeHold, $locked->idempotency_key.'-release');
            }

            if ($locked->payment_status === OrderPaymentStatus::Pending) {
                PaymentStateMachine::assert($locked->payment_status, OrderPaymentStatus::Failed);
                $locked->payment_status = OrderPaymentStatus::Failed;

                $authorized = Payment::query()
                    ->where('order_id', $locked->id)
                    ->where('status', PaymentStatus::Authorized)
                    ->lockForUpdate()
                    ->get();

                foreach ($authorized as $payment) {
                    $payment->status = PaymentStatus::Cancelled;
                    $payment->cancelled_at = now();
                    $payment->save();
                }
            }

            $locked->save();

            foreach ($locked->items as $item) {
                $profile = $item->esimProfile;
                if ($profile === null || in_array($profile->status, [EsimProfileStatus::Failed, EsimProfileStatus::Cancelled], true)) {
                    continue;
                }

                EsimStateMachine::assert($profile->status, EsimProfileStatus::Failed);
                $profile->status = EsimProfileStatus::Failed;
                $profile->save();
            }

            ($this->history)($locked, 'order', OrderStatus::Failed->value, $from->value, $locked->user, $reason);
            ($this->history)($locked, 'fulfillment', FulfillmentStatus::Failed->value, $fromFulfillment->value, $locked->user, $reason);

            if ($fromPayment !== $locked->payment_status) {
                ($this->history)($locked, 'payment', $locked->payment_status->value, $fromPayment->value, $locked->user, $reason);
            }

            return $locked->fresh(['items.esimProfile', 'payments']) ?? $locked;
        });

        event(new OrderFailed($order));

        return $order;
    }
}
