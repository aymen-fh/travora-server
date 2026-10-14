<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\Actions\Wallet\CreditWallet;
use App\Actions\Wallet\ReleaseWalletHold;
use App\Domain\MoneyMinor;
use App\Enums\EsimProfileStatus;
use App\Enums\FulfillmentStatus;
use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\WalletHoldStatus;
use App\Enums\WalletTransactionType;
use App\Exceptions\ForbiddenOperationException;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletHold;
use App\Services\Orders\RecordOrderStatus;
use App\Services\Payments\RecordOrderSettlement;
use App\States\EsimStateMachine;
use App\States\FulfillmentStateMachine;
use App\States\OrderStateMachine;
use Illuminate\Support\Facades\DB;

final class CancelOrder
{
    public function __construct(
        private readonly ReleaseWalletHold $releaseHold,
        private readonly CreditWallet $creditWallet,
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

            if ($locked->user_id !== $user->id) {
                throw new ForbiddenOperationException('Order does not belong to the actor.');
            }

            if ($locked->status === OrderStatus::Cancelled) {
                return $locked->fresh() ?? $locked;
            }

            if ($locked->fulfillment_status === FulfillmentStatus::Completed) {
                throw new ForbiddenOperationException('Completed fulfillments must be refunded, not cancelled.');
            }

            OrderStateMachine::assert($locked->status, OrderStatus::Cancelled);

            $activeHold = WalletHold::query()
                ->where('reference_type', Order::class)
                ->where('reference_id', $locked->id)
                ->where('status', WalletHoldStatus::Active)
                ->lockForUpdate()
                ->first();

            if ($activeHold !== null) {
                ($this->releaseHold)($user, $activeHold, $locked->idempotency_key.'-release');
            }

            Payment::query()
                ->where('order_id', $locked->id)
                ->where('status', PaymentStatus::Authorized)
                ->lockForUpdate()
                ->get()
                ->each(function (Payment $payment): void {
                    $payment->status = PaymentStatus::Cancelled;
                    $payment->cancelled_at = now();
                    $payment->save();
                });

            if ($locked->payment_status === OrderPaymentStatus::Paid) {
                $wallet = Wallet::query()
                    ->where('user_id', $user->id)
                    ->where('currency', $locked->currency)
                    ->lockForUpdate()
                    ->firstOrFail();

                ($this->creditWallet)(
                    $user,
                    $wallet,
                    MoneyMinor::of($locked->paid_minor, $locked->currency),
                    WalletTransactionType::Refund,
                    'Cancel '.$locked->order_number,
                    $locked->idempotency_key.'-refund',
                    Order::class,
                    $locked->id,
                );

                $payment = Payment::query()->where('order_id', $locked->id)->latest('created_at')->lockForUpdate()->first();
                if ($payment !== null && $payment->status !== PaymentStatus::Refunded) {
                    ($this->settlement)->refund(
                        $payment,
                        $locked,
                        $payment->amount_minor,
                        $payment->currency,
                        'order cancelled',
                        $locked->idempotency_key.'-refund-record',
                    );
                }
            }

            if ($locked->fulfillment_status !== FulfillmentStatus::Cancelled) {
                FulfillmentStateMachine::assert($locked->fulfillment_status, FulfillmentStatus::Cancelled);
            }

            $from = $locked->status;
            $locked->status = OrderStatus::Cancelled;
            $locked->fulfillment_status = FulfillmentStatus::Cancelled;
            $locked->cancelled_at = now();
            $locked->save();

            $locked->loadMissing('items.esimProfile');
            foreach ($locked->items as $item) {
                $profile = $item->esimProfile;
                if ($profile === null || in_array($profile->status, [EsimProfileStatus::Cancelled, EsimProfileStatus::Expired], true)) {
                    continue;
                }

                EsimStateMachine::assert($profile->status, EsimProfileStatus::Cancelled);
                $profile->status = EsimProfileStatus::Cancelled;
                $profile->save();
            }

            ($this->history)($locked, 'order', OrderStatus::Cancelled->value, $from->value, $user, 'cancelled');

            return $locked->fresh() ?? $locked;
        });
    }
}
