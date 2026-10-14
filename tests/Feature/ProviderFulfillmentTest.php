<?php

declare(strict_types=1);

use App\Actions\Orders\FulfillOrder;
use App\Actions\Orders\ReconcileOrderFulfillment;
use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Provider;
use App\Models\ProviderApiLog;
use App\Models\WalletHold;
use App\Services\Orders\CompleteOrderFulfillment;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;

function purchaseCount(Provider $provider): int
{
    return ProviderApiLog::query()
        ->where('provider_id', $provider->id)
        ->where('endpoint', 'purchase')
        ->count();
}

it('completes a timed-out purchase when reconcile finds the eSIM without buying again', function (): void {
    Event::fake();
    $user = customer('80.00');
    $plan = mappedPricedPlan(5000, 'timeout');
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/orders', [
        'plan_id' => $plan->id,
        'quantity' => 1,
    ], [
        'Idempotency-Key' => 'fulfill-timeout-1',
    ])->assertCreated()
        ->assertJsonPath('data.status', OrderStatus::Completed->value);

    $order = Order::query()->where('user_id', $user->id)->firstOrFail();
    $provider = $plan->provider;

    expect($order->fulfillment_status)->toBe(FulfillmentStatus::Completed);
    expect($order->items()->with('esimProfile')->first()?->esimProfile)->not->toBeNull();
    expect(purchaseCount($provider))->toBe(1);

    app(FulfillOrder::class)($order->fresh());
    app(CompleteOrderFulfillment::class)($user, $order->fresh());

    expect(purchaseCount($provider))->toBe(1);
    expect(Order::query()->count())->toBe(1);
});

it('keeps a missing timeout processing then fails at the deadline without a second buy', function (): void {
    Event::fake();
    $user = customer('80.00');
    $plan = mappedPricedPlan(5000, 'timeout_missing');
    $wallet = $user->wallets()->firstOrFail();
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/orders', [
        'plan_id' => $plan->id,
        'quantity' => 1,
    ], [
        'Idempotency-Key' => 'fulfill-timeout-missing-1',
    ])->assertCreated()
        ->assertJsonPath('data.status', OrderStatus::Processing->value);

    $order = Order::query()->where('user_id', $user->id)->firstOrFail();
    $provider = $plan->provider;

    expect($order->fulfillment_status)->toBe(FulfillmentStatus::Processing);
    expect($order->items()->with('esimProfile')->first()?->esimProfile?->status->value)->toBe('provisioning');
    expect($order->items()->with('esimProfile')->first()?->esimProfile?->iccid)->toBeNull();
    expect(purchaseCount($provider))->toBe(1);
    expect($wallet->refresh()->held_balance_minor)->toBe(5000);

    app(CompleteOrderFulfillment::class)($user, $order->fresh());
    expect(purchaseCount($provider))->toBe(1);
    expect($order->fresh()->status)->toBe(OrderStatus::Processing);

    $reconciled = app(ReconcileOrderFulfillment::class)($order->fresh(), true);

    expect($reconciled->status)->toBe(OrderStatus::Processing);
    expect($reconciled->fulfillment_status)->toBe(FulfillmentStatus::Processing);
    expect($reconciled->metadata['reconciliation_status'])->toBe('manual_review_required');
    expect(purchaseCount($provider))->toBe(1);
    expect(WalletHold::query()->where('reference_id', $reconciled->id)->first()?->status->value)->toBe('active');
    expect($wallet->refresh()->available_balance_minor)->toBe(3000);
    expect($wallet->refresh()->held_balance_minor)->toBe(5000);
});

it('fails the order on unusable credentials without reconciling as success', function (): void {
    Event::fake();
    $user = customer('80.00');
    $plan = mappedPricedPlan(5000, 'unusable_credentials');
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/orders', [
        'plan_id' => $plan->id,
        'quantity' => 1,
    ], [
        'Idempotency-Key' => 'fulfill-unusable-1',
    ])->assertCreated()
        ->assertJsonPath('data.status', OrderStatus::Failed->value);

    $order = Order::query()->where('user_id', $user->id)->firstOrFail();

    expect($order->fulfillment_status)->toBe(FulfillmentStatus::Failed);
    expect($order->items()->with('esimProfile')->first()?->esimProfile?->status->value)->toBe('failed');
    expect($order->items()->with('esimProfile')->first()?->esimProfile?->iccid)->toBeNull();
    expect(purchaseCount($plan->provider))->toBe(1);

    $again = app(\App\Services\Orders\CompleteOrderFulfillment::class)($user, $order->fresh());

    expect($again->status)->toBe(OrderStatus::Failed);
    expect(purchaseCount($plan->provider))->toBe(1);
});

it('fails a non-timeout provider outage without issuing a second purchase', function (): void {
    Event::fake();
    $user = customer('80.00');
    $plan = mappedPricedPlan(5000, 'unavailable');
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/orders', [
        'plan_id' => $plan->id,
        'quantity' => 1,
    ], [
        'Idempotency-Key' => 'fulfill-unavailable-1',
    ])->assertCreated()
        ->assertJsonPath('data.status', OrderStatus::Failed->value);

    $order = Order::query()->where('user_id', $user->id)->firstOrFail();

    expect($order->fulfillment_status)->toBe(FulfillmentStatus::Failed);
    expect(purchaseCount($plan->provider))->toBe(1);

    app(CompleteOrderFulfillment::class)($user, $order->fresh());

    expect(purchaseCount($plan->provider))->toBe(1);
    expect($order->fresh()->status)->toBe(OrderStatus::Failed);
});
