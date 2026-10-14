<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\OrderCompleted;
use App\Notifications\PurchaseCompletedNotification;

final class SendOrderCompletedNotification
{
    public function handle(OrderCompleted $event): void
    {
        $event->order->loadMissing('user');
        $event->order->user->notify(new PurchaseCompletedNotification($event->order));
    }
}
