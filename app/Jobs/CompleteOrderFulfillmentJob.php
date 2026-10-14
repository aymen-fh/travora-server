<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Order;
use App\Services\Orders\CompleteOrderFulfillment;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class CompleteOrderFulfillmentJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $uniqueFor = 3600;

    public int $backoff = 15;

    public function __construct(
        public readonly string $orderId,
    ) {}

    public function uniqueId(): string
    {
        return 'complete-order-fulfillment-'.$this->orderId;
    }

    public function handle(CompleteOrderFulfillment $complete): void
    {
        $order = Order::query()->with('user')->findOrFail($this->orderId);

        if ($order->user === null) {
            return;
        }

        $complete($order->user, $order);
    }
}
