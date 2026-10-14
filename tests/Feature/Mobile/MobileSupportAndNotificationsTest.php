<?php

declare(strict_types=1);

use App\Models\Notification;
use Laravel\Sanctum\Sanctum;

it('lets a customer create and view their own support ticket', function (): void {
    $user = customer();
    Sanctum::actingAs($user);

    $created = $this->postJson('/api/v1/support/tickets', [
        'subject' => 'Cannot install eSIM',
        'body' => 'QR code failed',
        'priority' => 'high',
        'category' => 'esim',
    ]);

    $created->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.subject', 'Cannot install eSIM')
        ->assertJsonPath('data.priority', 'high');

    $this->getJson('/api/v1/support/tickets/'.$created->json('data.id'))
        ->assertOk()
        ->assertJsonPath('data.messages.0.message', 'QR code failed');
});

it('marks a notification as read', function (): void {
    $user = customer();
    $notification = new Notification;
    $notification->user_id = $user->id;
    $notification->type = 'order';
    $notification->title = 'Order ready';
    $notification->body = 'Your eSIM is ready';
    $notification->save();

    Sanctum::actingAs($user);

    $this->postJson('/api/v1/notifications/'.$notification->id.'/read')
        ->assertOk();

    expect($notification->refresh()->read_at)->not->toBeNull();
});
