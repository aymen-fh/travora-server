<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\Domain\EsimFulfillmentResult;
use App\Enums\EsimProfileStatus;
use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Events\EsimReady;
use App\Events\OrderCompleted;
use App\Exceptions\ForbiddenOperationException;
use App\Models\EsimActivationEvent;
use App\Models\EsimProfile;
use App\Models\Order;
use App\Services\Orders\RecordOrderStatus;
use App\States\EsimStateMachine;
use App\States\FulfillmentStateMachine;
use App\States\OrderStateMachine;
use Illuminate\Support\Facades\DB;

final class ApplyEsimFulfillment
{
    public function __construct(
        private readonly RecordOrderStatus $history,
    ) {}

    public function __invoke(Order $order, EsimFulfillmentResult $result): Order
    {
        $order->loadMissing(['items.esimProfile', 'user']);

        if ($order->fulfillment_status === FulfillmentStatus::Completed) {
            return $order;
        }

        $profile = $this->targetProfile($order);

        if ($profile->status === EsimProfileStatus::Ready && $profile->external_profile_id === $result->externalProfileId) {
            return $order;
        }

        $order = DB::transaction(function () use ($order, $result, $profile): Order {
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $lockedProfile = EsimProfile::query()->whereKey($profile->id)->lockForUpdate()->firstOrFail();

            if ($lockedOrder->fulfillment_status === FulfillmentStatus::Completed) {
                return $lockedOrder;
            }

            if ($lockedProfile->status === EsimProfileStatus::Purchased) {
                EsimStateMachine::assert($lockedProfile->status, EsimProfileStatus::Provisioning);
                $lockedProfile->status = EsimProfileStatus::Provisioning;
            }

            EsimStateMachine::assert($lockedProfile->status, EsimProfileStatus::Ready);

            $lockedProfile->external_profile_id = $result->externalProfileId;
            $lockedProfile->iccid = $result->iccid;
            $lockedProfile->matching_id = $result->matchingId;
            $lockedProfile->smdp_address = $result->smdpAddress;
            $lockedProfile->activation_code_encrypted = $result->activationCode;
            $lockedProfile->confirmation_code_encrypted = $result->confirmationCode;
            $lockedProfile->data_total_mb = $result->dataTotalMb ?? $lockedProfile->data_total_mb;
            $lockedProfile->data_remaining_mb = $result->dataRemainingMb ?? $lockedProfile->data_remaining_mb;
            $lockedProfile->expires_at = $result->expiresAt;
            $lockedProfile->status = EsimProfileStatus::Ready;
            $lockedProfile->last_synced_at = now();
            $lockedProfile->save();

            $event = new EsimActivationEvent;
            $event->esim_profile_id = $lockedProfile->id;
            $event->event_type = 'provisioned';
            $event->status = 'ready';
            $event->provider_reference = $result->externalProfileId;
            $event->message = 'Provider fulfillment applied';
            $event->save();

            OrderStateMachine::assert($lockedOrder->status, OrderStatus::Completed);
            FulfillmentStateMachine::assert($lockedOrder->fulfillment_status, FulfillmentStatus::Completed);

            $from = $lockedOrder->status;
            $fromFulfillment = $lockedOrder->fulfillment_status;
            $lockedOrder->status = OrderStatus::Completed;
            $lockedOrder->fulfillment_status = FulfillmentStatus::Completed;
            $lockedOrder->completed_at = now();
            $lockedOrder->save();

            ($this->history)($lockedOrder, 'order', OrderStatus::Completed->value, $from->value, $lockedOrder->user, 'esim ready');
            ($this->history)($lockedOrder, 'fulfillment', FulfillmentStatus::Completed->value, $fromFulfillment->value, $lockedOrder->user);

            return $lockedOrder->fresh(['items.esimProfile', 'user']) ?? $lockedOrder;
        });

        $ready = $order->items->first()?->esimProfile;

        event(new OrderCompleted($order));
        if ($ready !== null) {
            event(new EsimReady($ready));
        }

        return $order;
    }

    private function targetProfile(Order $order): EsimProfile
    {
        foreach ($order->items as $item) {
            if ($item->esimProfile !== null) {
                return $item->esimProfile;
            }
        }

        throw new ForbiddenOperationException('Order has no eSIM profile to fulfill.');
    }
}
