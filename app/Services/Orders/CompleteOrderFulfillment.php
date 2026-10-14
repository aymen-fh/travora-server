<?php

declare(strict_types=1);

namespace App\Services\Orders;

use App\Actions\Orders\ApplyEsimFulfillment;
use App\Actions\Orders\CaptureReservedOrderPayment;
use App\Actions\Orders\FailOrderFulfillment;
use App\Actions\Orders\FulfillOrder;
use App\Actions\Orders\RequestEsimPurchase;
use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Exceptions\CapabilityNotSupportedException;
use App\Exceptions\CatalogUnavailableException;
use App\Integrations\Esim\Exceptions\ProviderException;
use App\Integrations\Esim\Exceptions\ProviderTimeoutException;
use App\Models\Order;
use App\Models\User;
use InvalidArgumentException;

final class CompleteOrderFulfillment
{
    public function __construct(
        private readonly FulfillOrder $fulfill,
        private readonly RequestEsimPurchase $purchase,
        private readonly ApplyEsimFulfillment $apply,
        private readonly CaptureReservedOrderPayment $capture,
        private readonly FailOrderFulfillment $fail,
    ) {}

    public function __invoke(User $user, Order $order): Order
    {
        $order = $order->fresh(['items.esimProfile', 'payments', 'user']) ?? $order;

        if ($order->fulfillment_status === FulfillmentStatus::Completed) {
            return ($this->capture)($user, $order);
        }

        if (in_array($order->status, [OrderStatus::Failed, OrderStatus::Cancelled], true)) {
            $attempted = (bool) (($order->metadata ?? [])['provider_purchase_attempted'] ?? false);
            if ($attempted) {
                try {
                    $result = ($this->purchase)($order);
                    if ($result !== null && $result->isCompleted() && $result->fulfillment !== null) {
                        $this->recordDiscrepancy($order, $result);
                    }
                } catch (\Throwable) {
                    // Suppress transient lookup errors during discrepancy check
                }
            }

            return $order;
        }

        $order = ($this->fulfill)($order);

        try {
            $result = ($this->purchase)($order);
        } catch (ProviderTimeoutException) {
            return $order->fresh(['items.esimProfile', 'payments', 'user']) ?? $order;
        } catch (ProviderException|CatalogUnavailableException|CapabilityNotSupportedException|InvalidArgumentException $exception) {
            return ($this->fail)($order, $exception->getMessage());
        }

        if ($result !== null && $result->isCompleted() && $result->fulfillment !== null) {
            $order = ($this->apply)($order, $result->fulfillment);

            return ($this->capture)($user, $order);
        }

        return $order->fresh(['items.esimProfile', 'payments', 'user']) ?? $order;
    }

    private function recordDiscrepancy(Order $order, \App\Integrations\Esim\DTOs\ProviderPurchaseResult $result): void
    {
        $metadata = $order->metadata ?? [];
        if (isset($metadata['unreconciled_provider_fulfillment'])) {
            return;
        }

        $fulfillment = $result->fulfillment;
        $metadata['reconciliation_status'] = 'financial_discrepancy_detected';
        $metadata['unreconciled_provider_fulfillment'] = [
            'provider_order_no' => $result->externalOrderId,
            'external_profile_id' => $fulfillment?->externalProfileId,
            'iccid' => $fulfillment?->iccid,
            'detected_at' => now()->toIso8601String(),
            'notes' => 'Provider delivered fulfillment for cancelled/failed order. Wholesale liability incurred without customer debit.',
        ];
        $order->metadata = $metadata;
        $order->save();

        \Illuminate\Support\Facades\Log::critical("CRITICAL FINANCIAL DISCREPANCY: Provider delivered eSIM fulfillment for cancelled/failed order [{$order->id}]. Wholesale liability incurred without customer debit. ICCID: [{$fulfillment?->iccid}], Provider Order: [{$result->externalOrderId}].", [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'provider_order_no' => $result->externalOrderId,
            'iccid' => $fulfillment?->iccid,
            'order_status' => $order->status->value,
            'total_minor' => $order->total_minor,
        ]);
    }
}
