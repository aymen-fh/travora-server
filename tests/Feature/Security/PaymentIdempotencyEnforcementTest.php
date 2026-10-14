<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;

it('requires an idempotency key for order checkout', function (): void {
    Event::fake();
    $user = customer('80.00');
    $plan = mappedPricedPlan();
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/orders', [
        'plan_id' => $plan->id,
        'quantity' => 1,
    ])->assertStatus(428)
        ->assertJsonPath('success', false);
});

it('requires an idempotency key for wallet top-up', function (): void {
    $user = customer('10.00');
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/wallet/topup', [
        'amount_minor' => 500,
        'currency' => 'LYD',
    ])->assertStatus(428)
        ->assertJsonPath('success', false);
});
