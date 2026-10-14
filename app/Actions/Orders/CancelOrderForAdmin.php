<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\Actions\Audit\RecordAuditLog;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Request;

final class CancelOrderForAdmin
{
    public function __construct(
        private readonly CancelOrder $cancel,
        private readonly RecordAuditLog $audit,
    ) {}

    public function __invoke(User $actor, Order $order, string $reason, ?Request $request = null): Order
    {
        $order->loadMissing('user');
        $owner = $order->user ?? throw new \InvalidArgumentException('Order owner is missing.');

        $cancelled = ($this->cancel)($owner, $order);

        ($this->audit)(
            'order.cancelled',
            $actor,
            Order::class,
            $order->id,
            null,
            ['reason' => $reason, 'status' => $cancelled->status?->value],
            $request?->ip(),
            $request?->userAgent(),
        );

        return $cancelled;
    }
}
