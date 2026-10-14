<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\Actions\Audit\RecordAuditLog;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Request;

final class RetryOrderFulfillment
{
    public function __construct(
        private readonly ReconcileOrderFulfillment $reconcile,
        private readonly RecordAuditLog $audit,
    ) {}

    public function __invoke(User $actor, Order $order, ?Request $request = null): Order
    {
        $reconciled = ($this->reconcile)($order, false);

        ($this->audit)(
            'order.retry_fulfillment',
            $actor,
            Order::class,
            $order->id,
            null,
            [
                'status' => $reconciled->status?->value,
                'fulfillment_status' => $reconciled->fulfillment_status?->value,
            ],
            $request?->ip(),
            $request?->userAgent(),
        );

        return $reconciled;
    }
}
