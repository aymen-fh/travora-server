<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Orders\FulfillOrder;
use App\Models\Order;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class FulfillOrderJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $uniqueFor = 3600;

    public function __construct(
        public readonly string $orderId,
    ) {}

    public function uniqueId(): string
    {
        return 'fulfill-order-'.$this->orderId;
    }

    public function handle(FulfillOrder $fulfill): void
    {
        $order = Order::query()->findOrFail($this->orderId);
        $fulfill($order);
    }
}
