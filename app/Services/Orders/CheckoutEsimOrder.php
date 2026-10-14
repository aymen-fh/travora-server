<?php

declare(strict_types=1);

namespace App\Services\Orders;

use App\Actions\Orders\CreateOrder;
use App\Actions\Orders\ReserveOrderWithWallet;
use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Models\EsimPlan;
use App\Models\Order;
use App\Models\User;
use App\Models\Wallet;

final class CheckoutEsimOrder
{
    public function __construct(
        private readonly CreateOrder $create,
        private readonly ReserveOrderWithWallet $reserve,
        private readonly CompleteOrderFulfillment $complete,
    ) {}

    public function __invoke(User $user, EsimPlan $plan, Wallet $wallet, string $idempotencyKey, int $quantity = 1): Order
    {
        $order = ($this->create)($user, $plan, $idempotencyKey, $quantity);

        if ($order->status === OrderStatus::Cancelled) {
            return $order;
        }

        if ($order->status === OrderStatus::Failed) {
            return $order;
        }

        if ($order->fulfillment_status === FulfillmentStatus::Completed) {
            return ($this->complete)($user, $order);
        }

        $order = ($this->reserve)($user, $order, $wallet);

        return ($this->complete)($user, $order);
    }
}
