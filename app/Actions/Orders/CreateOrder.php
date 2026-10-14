<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\Actions\Catalog\QuoteEsimPlan;
use App\Domain\OrderTotals;
use App\Domain\References\AssertReference;
use App\Enums\FulfillmentStatus;
use App\Enums\OrderItemType;
use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Exceptions\ForbiddenOperationException;
use App\Models\EsimPlan;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Services\Orders\RecordOrderStatus;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CreateOrder
{
    public function __construct(
        private readonly QuoteEsimPlan $quote,
        private readonly RecordOrderStatus $history,
        private readonly AssertReference $references,
    ) {}

    public function __invoke(User $user, EsimPlan $plan, string $idempotencyKey, int $quantity = 1): Order
    {
        if (! $user->isActive()) {
            throw new ForbiddenOperationException('Inactive users cannot place orders.');
        }

        $existing = Order::query()->where('idempotency_key', $idempotencyKey)->first();
        if ($existing !== null) {
            return $existing->load('items');
        }

        $price = ($this->quote)($plan);
        $quantity = max(1, $quantity);
        $totals = new OrderTotals(
            subtotalMinor: $price->price_minor * $quantity,
            discountMinor: 0,
            taxMinor: 0,
            currency: $price->currency,
        );

        try {
            return DB::transaction(function () use ($user, $plan, $idempotencyKey, $quantity, $price, $totals): Order {
                $order = new Order;
                $order->user_id = $user->id;
                $order->order_number = 'TRV-'.now()->format('Ymd').'-'.strtoupper(Str::random(6));
                $order->status = OrderStatus::Pending;
                $order->payment_status = OrderPaymentStatus::Pending;
                $order->fulfillment_status = FulfillmentStatus::Pending;
                $order->currency = $totals->currency;
                $order->subtotal_minor = $totals->subtotalMinor;
                $order->discount_minor = $totals->discountMinor;
                $order->tax_minor = $totals->taxMinor;
                $order->total_minor = $totals->totalMinor();
                $order->customer_email_snapshot = $user->email;
                $order->customer_phone_snapshot = $user->phone;
                $order->idempotency_key = $idempotencyKey;
                $order->metadata = [
                    'quoted_at' => now()->toIso8601String(),
                    'quote_currency' => $price->currency,
                    'quote_price_minor' => $price->price_minor,
                    'quote_price_id' => $price->id,
                ];
                $order->placed_at = now();
                $order->save();

                ($this->references)->orderItem(OrderItemType::EsimPlan, $plan->id);

                $item = new OrderItem;
                $item->order_id = $order->id;
                $item->item_type = OrderItemType::EsimPlan;
                $item->reference_type = EsimPlan::class;
                $item->reference_id = $plan->id;
                $item->provider_id = $plan->provider_id;
                $item->name_snapshot = $plan->name;
                $item->description_snapshot = $plan->description;
                $item->quantity = $quantity;
                $item->unit_price_minor = $price->price_minor;
                $item->discount_minor = $totals->discountMinor;
                $item->tax_minor = $totals->taxMinor;
                $item->total_minor = $totals->totalMinor();
                $item->metadata = [
                    'plan_id' => $plan->id,
                    'price_id' => $price->id,
                    'currency' => $price->currency,
                    'quoted_price_minor' => $price->price_minor,
                    'data_mb' => $plan->data_mb,
                    'validity_days' => $plan->validity_days,
                    'is_unlimited' => $plan->is_unlimited,
                ];
                $item->save();

                ($this->history)($order, 'order', OrderStatus::Pending->value, null, $user, 'order created');

                return $order->load('items');
            });
        } catch (UniqueConstraintViolationException) {
            return Order::query()->where('idempotency_key', $idempotencyKey)->firstOrFail()->load('items');
        }
    }
}
