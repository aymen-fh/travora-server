<?php

declare(strict_types=1);

use App\Actions\Audit\RecordAuditLog;
use App\Models\Order;

it('writes an audit log for an actor and entity', function (): void {
    $user = customer('10.00');
    $order = Order::factory()->create(['user_id' => $user->id]);

    $log = app(RecordAuditLog::class)(
        'order.created',
        $user,
        Order::class,
        $order->id,
        null,
        ['status' => 'pending'],
        '127.0.0.1',
        'Pest',
    );

    expect($log->action)->toBe('order.created');
    expect($log->user_id)->toBe($user->id);
    expect($log->entity_id)->toBe($order->id);
    expect($log->new_values)->toBe(['status' => 'pending']);
});
