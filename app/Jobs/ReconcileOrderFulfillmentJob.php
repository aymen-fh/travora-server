<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Orders\ReconcileOrderFulfillment;
use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Jobs\SyncJob;

final class ReconcileOrderFulfillmentJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 8;

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [15, 30, 60, 120];
    }

    public function __construct(
        public readonly string $orderId,
        public readonly bool $deadlineReached = false,
    ) {}

    public function handle(ReconcileOrderFulfillment $reconcile): void
    {
        $order = Order::query()->findOrFail($this->orderId);
        $order = $reconcile($order, $this->deadlineReached);

        if ($order->status !== OrderStatus::Processing) {
            return;
        }

        if ($this->job instanceof SyncJob) {
            return;
        }

        $index = max(0, min($this->attempts(), count($this->backoff())) - 1);
        $this->release($this->backoff()[$index]);
    }
}
