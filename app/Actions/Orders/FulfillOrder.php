<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\Enums\EsimProfileStatus;
use App\Enums\FulfillmentStatus;
use App\Enums\OrderItemType;
use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Enums\WalletHoldStatus;
use App\Exceptions\ForbiddenOperationException;
use App\Models\EsimPlan;
use App\Models\EsimProfile;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\WalletHold;
use App\Services\Orders\RecordOrderStatus;
use App\States\EsimStateMachine;
use App\States\FulfillmentStateMachine;
use App\States\OrderStateMachine;
use Illuminate\Support\Facades\DB;

final class FulfillOrder
{
    public function __construct(
        private readonly RecordOrderStatus $history,
    ) {}

    public function __invoke(Order $order): Order
    {
        $order->loadMissing(['user', 'items']);

        if ($order->fulfillment_status === FulfillmentStatus::Completed) {
            return $order;
        }

        if (! $this->fundsReserved($order)) {
            throw new ForbiddenOperationException('Unpaid orders cannot be fulfilled.');
        }

        return DB::transaction(function () use ($order): Order {
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $locked->loadMissing(['user', 'items']);

            if ($locked->fulfillment_status === FulfillmentStatus::Completed) {
                return $locked;
            }

            if (! $this->fundsReserved($locked)) {
                throw new ForbiddenOperationException('Unpaid orders cannot be fulfilled.');
            }

            if ($locked->fulfillment_status === FulfillmentStatus::Pending) {
                OrderStateMachine::assert($locked->status, OrderStatus::Processing);
                FulfillmentStateMachine::assert($locked->fulfillment_status, FulfillmentStatus::Processing);

                $from = $locked->status;
                $fromFulfillment = $locked->fulfillment_status;
                $locked->status = OrderStatus::Processing;
                $locked->fulfillment_status = FulfillmentStatus::Processing;
                $locked->save();

                ($this->history)($locked, 'order', OrderStatus::Processing->value, $from->value, $locked->user, 'fulfillment started');
                ($this->history)($locked, 'fulfillment', FulfillmentStatus::Processing->value, $fromFulfillment->value, $locked->user);
            }

            foreach ($locked->items as $item) {
                $this->provisionItem($locked, $item);
            }

            return $locked->fresh(['items.esimProfile', 'user']) ?? $locked;
        });
    }

    private function provisionItem(Order $order, OrderItem $item): void
    {
        if ($item->item_type !== OrderItemType::EsimPlan) {
            return;
        }

        $existing = EsimProfile::query()->where('order_item_id', $item->id)->first();
        if ($existing !== null) {
            if ($existing->status === EsimProfileStatus::Purchased) {
                EsimStateMachine::assert($existing->status, EsimProfileStatus::Provisioning);
                $existing->status = EsimProfileStatus::Provisioning;
                $existing->save();
            }

            return;
        }

        $plan = EsimPlan::query()->find($item->reference_id);

        $profile = new EsimProfile;
        $profile->user_id = $order->user_id;
        $profile->order_item_id = $item->id;
        $profile->plan_id = $item->reference_id;
        $profile->provider_id = $item->provider_id;
        $profile->status = EsimProfileStatus::Purchased;
        $profile->data_total_mb = $plan?->data_mb;
        $profile->data_remaining_mb = $plan?->data_mb;
        $profile->save();

        EsimStateMachine::assert($profile->status, EsimProfileStatus::Provisioning);
        $profile->status = EsimProfileStatus::Provisioning;
        $profile->save();
    }

    private function fundsReserved(Order $order): bool
    {
        if ($order->payment_status === OrderPaymentStatus::Paid) {
            return true;
        }

        return WalletHold::query()
            ->where('reference_type', Order::class)
            ->where('reference_id', $order->id)
            ->where('status', WalletHoldStatus::Active)
            ->exists();
    }
}
