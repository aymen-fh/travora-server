<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class SweepStaleFulfillmentsJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $uniqueFor = 120;

    public function uniqueId(): string
    {
        return 'sweep-stale-fulfillments';
    }

    public function handle(): void
    {
        $ttl = max(1, (int) config('esim.reconcile_ttl', 300));
        $cutoff = now()->subSeconds($ttl);

        Order::query()
            ->where('status', OrderStatus::Processing)
            ->where('fulfillment_status', FulfillmentStatus::Processing)
            ->where('updated_at', '<', $cutoff)
            ->orderBy('id')
            ->each(function (Order $order): void {
                ReconcileOrderFulfillmentJob::dispatch($order->id, true);
            });
    }
}
