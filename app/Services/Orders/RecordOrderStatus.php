<?php

declare(strict_types=1);

namespace App\Services\Orders;

use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\User;

final class RecordOrderStatus
{
    public function __invoke(
        Order $order,
        string $stateType,
        string $to,
        ?string $from = null,
        ?User $actor = null,
        ?string $reason = null,
    ): void {
        $history = new OrderStatusHistory;
        $history->order_id = $order->id;
        $history->state_type = $stateType;
        $history->from_value = $from;
        $history->to_value = $to;
        $history->reason = $reason;
        $history->created_by = $actor?->id;
        $history->save();
    }
}
