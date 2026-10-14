<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\Enums\EsimProfileStatus;
use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\Orders\CompleteOrderFulfillment;

final class ReconcileOrderFulfillment
{
    public function __construct(
        private readonly CompleteOrderFulfillment $complete,
        private readonly FailOrderFulfillment $fail,
    ) {}

    public function __invoke(Order $order, bool $deadlineReached = false): Order
    {
        $order->loadMissing(['items.esimProfile', 'user']);

        if (in_array($order->fulfillment_status, [FulfillmentStatus::Completed, FulfillmentStatus::Cancelled], true)) {
            return $order;
        }

        if ($order->status !== OrderStatus::Processing) {
            return $order;
        }

        if ($order->user !== null) {
            $order = ($this->complete)($order->user, $order);
        }

        if (in_array($order->fulfillment_status, [FulfillmentStatus::Completed, FulfillmentStatus::Cancelled], true)) {
            return $order;
        }

        $order->loadMissing('items.esimProfile');
        $profiles = $order->items->map(fn ($item) => $item->esimProfile)->filter();
        $allReady = $profiles->isNotEmpty()
            && $profiles->every(fn ($profile) => $profile->status === EsimProfileStatus::Ready);

        if ($allReady) {
            $order->status = OrderStatus::Completed;
            $order->fulfillment_status = FulfillmentStatus::Completed;
            $order->completed_at = now();
            $order->save();

            return $order->fresh(['items.esimProfile']) ?? $order;
        }

        if ($deadlineReached) {
            $attempted = (bool) (($order->metadata ?? [])['provider_purchase_attempted'] ?? false);
            $retainAmbiguous = (bool) config('esim.retain_ambiguous_holds', false);

            if ($attempted && $retainAmbiguous) {
                $metadata = $order->metadata ?? [];
                $metadata['reconciliation_status'] = 'manual_review_required';
                $metadata['reconciliation_deadline_reached_at'] = now()->toIso8601String();
                $metadata['reconciliation_alert'] = 'Provider purchase was attempted but outcome remains ambiguous at reconcile deadline. Retaining wallet hold pending administrative review.';
                $order->metadata = $metadata;
                $order->save();

                \Illuminate\Support\Facades\Log::warning("Order [{$order->id}] escalated to manual_review_required: provider purchase was attempted but outcome unconfirmed at reconcile deadline.", [
                    'order_id' => $order->id,
                    'provider_transaction_id' => $metadata['provider_transaction_id'] ?? null,
                    'user_id' => $order->user_id,
                    'total_minor' => $order->total_minor,
                ]);

                return $order->fresh(['items.esimProfile', 'payments']) ?? $order;
            }

            return ($this->fail)($order, 'Fulfillment could not be confirmed before the reconcile deadline.');
        }

        return $order;
    }
}
