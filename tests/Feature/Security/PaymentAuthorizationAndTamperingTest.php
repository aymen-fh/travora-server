<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;

it('forbids reading another user order', function (): void {
    Event::fake();

    $owner = customer('80.00');
    $plan = mappedPricedPlan();
    Sanctum::actingAs($owner);

    $created = $this->postJson('/api/v1/orders', [
        'plan_id' => $plan->id,
        'quantity' => 1,
    ], [
        'Idempotency-Key' => 'audit-owner-order-read',
    ])->assertCreated();

    $intruder = customer('80.00');
    Sanctum::actingAs($intruder);

    $this->getJson('/api/v1/orders/'.$created->json('data.id'))
        ->assertForbidden()
        ->assertJsonPath('success', false)
        ->assertJsonPath('code', 'FORBIDDEN');
});

it('forbids cancelling another user order', function (): void {
    Event::fake();

    $owner = customer('80.00');
    $plan = mappedPricedPlan();
    Sanctum::actingAs($owner);

    $created = $this->postJson('/api/v1/orders', [
        'plan_id' => $plan->id,
        'quantity' => 1,
    ], [
        'Idempotency-Key' => 'audit-owner-order-cancel',
    ])->assertCreated();

    $intruder = customer('80.00');
    Sanctum::actingAs($intruder);

    $this->postJson('/api/v1/orders/'.$created->json('data.id').'/cancel')
        ->assertForbidden()
        ->assertJsonPath('success', false)
        ->assertJsonPath('code', 'FORBIDDEN');
});

it('ignores client supplied amount and currency tampering fields on checkout', function (): void {
    Event::fake();

    $user = customer('80.00');
    $plan = mappedPricedPlan(5000);
    Sanctum::actingAs($user);

    $response = $this->postJson('/api/v1/orders', [
        'plan_id' => $plan->id,
        'quantity' => 1,
        'amount_minor' => 1,
        'currency' => 'USD',
    ], [
        'Idempotency-Key' => 'audit-tamper-amount-currency',
    ])->assertCreated();

    $response
        ->assertJsonPath('data.total_minor', 5000)
        ->assertJsonPath('data.currency', 'LYD');
});
