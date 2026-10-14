<?php

declare(strict_types=1);

use App\Actions\Orders\CancelOrder;
use App\Actions\Orders\CreateOrder;
use App\Actions\Orders\PayOrderWithWallet;
use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Models\PaymentRefund;
use Laravel\Sanctum\Sanctum;

it('cancelling a paid order is idempotent even when the caller reuses a stale order object', function (): void {
    $user = customer('80.00');
    $plan = pricedPlan(5000);
    $order = app(CreateOrder::class)($user, $plan, 'audit-cancel-idem');
    $paid = app(PayOrderWithWallet::class)($user, $order, $user->wallets()->firstOrFail());

    // Use the same stale instance for both calls to simulate retries/races.
    $cancel = app(CancelOrder::class);
    $first = $cancel($user, $paid);
    $second = $cancel($user, $paid);

    expect($first->status)->toBe(OrderStatus::Cancelled);
    expect($first->payment_status)->toBe(OrderPaymentStatus::Refunded);
    expect($second->status)->toBe(OrderStatus::Cancelled);
    expect($second->payment_status)->toBe(OrderPaymentStatus::Refunded);
    expect($user->wallets()->firstOrFail()->refresh()->available_balance_minor)->toBe(8000);
    expect(PaymentRefund::query()->count())->toBe(1);
});

it('completes checkout successfully without faking events', function (): void {
    $user = customer('80.00');
    $plan = mappedPricedPlan();
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/orders', [
        'plan_id' => $plan->id,
        'quantity' => 1,
    ], [
        'Idempotency-Key' => 'audit-real-checkout-notify',
    ])->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.status', OrderStatus::Completed->value);
});
