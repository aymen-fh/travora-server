<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\Enums\OrderItemType;
use App\Enums\ProviderCapability;
use App\Exceptions\CatalogUnavailableException;
use App\Integrations\Esim\DTOs\ProviderPurchaseResult;
use App\Integrations\Esim\DTOs\PurchaseRequest;
use App\Integrations\Esim\Exceptions\ProviderTimeoutException;
use App\Integrations\Esim\Manager\ProviderManager;
use App\Models\EsimPlan;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Provider;
use App\Models\ProviderExternalReference;

final class RequestEsimPurchase
{
    public function __construct(
        private readonly ProviderManager $providers,
    ) {}

    public function __invoke(Order $order): ?ProviderPurchaseResult
    {
        $order->loadMissing(['items']);

        $item = $this->planItem($order);
        $provider = Provider::query()->find($item->provider_id);

        if ($provider === null) {
            throw new CatalogUnavailableException('Order item has no provider to purchase from.');
        }

        $driver = $this->providers->driver($provider);
        $this->providers->assertCapability($driver, ProviderCapability::Purchase);

        $externalPlanId = $this->externalPlanId($item);
        $key = $order->idempotency_key ?? $order->id;
        $attempted = (bool) (($order->metadata ?? [])['provider_purchase_attempted'] ?? false);

        if ($attempted) {
            $this->providers->assertCapability($driver, ProviderCapability::EsimLookup);

            return $driver->findPurchase($key);
        }

        $this->markAttempted($order);

        try {
            $result = $driver->purchase(new PurchaseRequest($externalPlanId, $key, $item->quantity));
        } catch (ProviderTimeoutException $exception) {
            $this->providers->assertCapability($driver, ProviderCapability::EsimLookup);
            $found = $driver->findPurchase($key);

            if ($found !== null) {
                $this->rememberExternalOrder($order, $found);

                return $found;
            }

            throw $exception;
        }

        $this->rememberExternalOrder($order, $result);

        return $result;
    }

    private function planItem(Order $order): OrderItem
    {
        foreach ($order->items as $item) {
            if ($item->item_type === OrderItemType::EsimPlan) {
                return $item;
            }
        }

        throw new CatalogUnavailableException('Order has no eSIM plan item to purchase.');
    }

    private function externalPlanId(OrderItem $item): string
    {
        $reference = ProviderExternalReference::query()
            ->where('provider_id', $item->provider_id)
            ->where('entity_type', 'esim_plan')
            ->where('entity_id', $item->reference_id)
            ->first();

        if ($reference !== null && $reference->external_id !== '') {
            return $reference->external_id;
        }

        $plan = EsimPlan::query()->with('providerProduct')->find($item->reference_id);
        $externalProductId = $plan?->providerProduct?->external_product_id;

        if (is_string($externalProductId) && $externalProductId !== '') {
            return $externalProductId;
        }

        throw new CatalogUnavailableException('Plan is not mapped to a provider product.');
    }

    private function markAttempted(Order $order): void
    {
        $metadata = $order->metadata ?? [];
        $metadata['provider_purchase_attempted'] = true;
        $metadata['provider_purchase_attempted_at'] = now()->toIso8601String();
        $metadata['provider_transaction_id'] = $order->idempotency_key ?? $order->id;
        $order->metadata = $metadata;
        $order->save();
    }

    private function rememberExternalOrder(Order $order, ProviderPurchaseResult $result): void
    {
        $metadata = $order->metadata ?? [];
        $metadata['provider_external_order_id'] = $result->externalOrderId;
        $order->metadata = $metadata;
        $order->save();
    }
}
