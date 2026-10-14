<?php

declare(strict_types=1);

use App\Actions\Orders\ApplyEsimFulfillment;
use App\Actions\Orders\CancelOrder;
use App\Actions\Orders\CreateOrder;
use App\Actions\Orders\FailOrderFulfillment;
use App\Actions\Orders\FulfillOrder;
use App\Actions\Orders\PayOrderWithWallet;
use App\Domain\EsimFulfillmentResult;
use App\Enums\EsimProfileStatus;
use App\Enums\FulfillmentStatus;
use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Events\EsimReady;
use App\Events\OrderCompleted;
use App\Events\OrderFailed;
use App\Exceptions\CatalogUnavailableException;
use App\Exceptions\ForbiddenOperationException;
use App\Exceptions\InsufficientBalanceException;
use App\Models\EsimPlan;
use App\Models\Payment;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\Event;

it('creates an order from the active plan price and reuses the idempotency key', function (): void {
    $user = customer('100.00');
    $plan = pricedPlan(5000);

    $create = app(CreateOrder::class);
    $first = $create($user, $plan, 'order-idem-1');
    $second = $create($user, $plan, 'order-idem-1');

    expect($first->id)->toBe($second->id);
    expect($first->status)->toBe(OrderStatus::Pending);
    expect($first->total_minor)->toBe(5000);
    expect($first->items)->toHaveCount(1);
    expect($first->items->first()->unit_price_minor)->toBe(5000);
});

it('rejects an order when the plan has no active price', function (): void {
    $user = customer('100.00');
    $plan = EsimPlan::factory()->create();

    app(CreateOrder::class)($user, $plan, 'order-no-price');
})->throws(CatalogUnavailableException::class);

it('pays an order from the wallet, allocates the payment, and is idempotent', function (): void {
    $user = customer('80.00');
    $plan = pricedPlan(5000);
    $order = app(CreateOrder::class)($user, $plan, 'order-pay-1');
    $wallet = $user->wallets()->firstOrFail();

    $pay = app(PayOrderWithWallet::class);
    $paid = $pay($user, $order, $wallet);
    $replayed = $pay($user, $order->fresh(), $wallet);

    expect($paid->id)->toBe($replayed->id);
    expect($paid->status)->toBe(OrderStatus::Confirmed);
    expect($paid->payment_status)->toBe(OrderPaymentStatus::Paid);
    expect($paid->paid_minor)->toBe(5000);
    expect($wallet->refresh()->available_balance_minor)->toBe(3000);
    expect(Payment::query()->where('order_id', $paid->id)->count())->toBe(1);
    expect(WalletTransaction::query()->where('wallet_id', $wallet->id)->count())->toBe(2);
});

it('rejects payment when the wallet cannot cover the order', function (): void {
    $user = customer('10.00');
    $plan = pricedPlan(5000);
    $order = app(CreateOrder::class)($user, $plan, 'order-broke');

    app(PayOrderWithWallet::class)($user, $order, $user->wallets()->firstOrFail());
})->throws(InsufficientBalanceException::class);

it('cancels an unpaid order without touching the wallet', function (): void {
    $user = customer('80.00');
    $plan = pricedPlan(5000);
    $order = app(CreateOrder::class)($user, $plan, 'order-cancel-unpaid');

    $cancelled = app(CancelOrder::class)($user, $order);

    expect($cancelled->status)->toBe(OrderStatus::Cancelled);
    expect($cancelled->fulfillment_status)->toBe(FulfillmentStatus::Cancelled);
    expect($user->wallets()->first()->refresh()->available_balance_minor)->toBe(8000);
});

it('refunds a paid order back to the wallet', function (): void {
    $user = customer('80.00');
    $plan = pricedPlan(5000);
    $order = app(CreateOrder::class)($user, $plan, 'order-cancel-paid');
    $wallet = $user->wallets()->firstOrFail();
    $paid = app(PayOrderWithWallet::class)($user, $order, $wallet);

    $cancelled = app(CancelOrder::class)($user, $paid);

    expect($cancelled->status)->toBe(OrderStatus::Cancelled);
    expect($cancelled->payment_status)->toBe(OrderPaymentStatus::Refunded);
    expect($wallet->refresh()->available_balance_minor)->toBe(8000);
});

it('refuses to cancel a completed fulfillment', function (): void {
    Event::fake([OrderCompleted::class, EsimReady::class]);

    $user = customer('80.00');
    $plan = pricedPlan(5000);
    $order = app(CreateOrder::class)($user, $plan, 'order-complete');
    $paid = app(PayOrderWithWallet::class)($user, $order, $user->wallets()->firstOrFail());
    $fulfilling = app(FulfillOrder::class)($paid);
    $completed = app(ApplyEsimFulfillment::class)($fulfilling, new EsimFulfillmentResult(
        externalProfileId: 'ext-1',
        iccid: '8900000000000000001',
        activationCode: 'LPA:1$smdp$matching',
        dataTotalMb: 1024,
        dataRemainingMb: 1024,
    ));

    expect($completed->status)->toBe(OrderStatus::Completed);

    app(CancelOrder::class)($user, $completed);
})->throws(ForbiddenOperationException::class);

it('provisions an eSIM profile and applies fulfillment after commit events', function (): void {
    Event::fake([OrderCompleted::class, EsimReady::class]);

    $user = customer('80.00');
    $plan = pricedPlan(5000);
    $order = app(CreateOrder::class)($user, $plan, 'order-fulfill');
    $paid = app(PayOrderWithWallet::class)($user, $order, $user->wallets()->firstOrFail());
    $fulfilling = app(FulfillOrder::class)($paid);

    expect($fulfilling->status)->toBe(OrderStatus::Processing);
    expect($fulfilling->fulfillment_status)->toBe(FulfillmentStatus::Processing);
    expect($fulfilling->items->first()->esimProfile?->status)->toBe(EsimProfileStatus::Provisioning);

    $completed = app(ApplyEsimFulfillment::class)($fulfilling, new EsimFulfillmentResult(
        externalProfileId: 'ext-ready',
        iccid: '8900000000000000002',
        matchingId: 'match-1',
        smdpAddress: 'smdp.example',
        activationCode: 'secret-code',
        dataTotalMb: 1024,
        dataRemainingMb: 1024,
    ));

    $profile = $completed->items->first()->esimProfile;
    expect($completed->status)->toBe(OrderStatus::Completed);
    expect($profile?->status)->toBe(EsimProfileStatus::Ready);
    expect($profile?->external_profile_id)->toBe('ext-ready');
    Event::assertDispatched(OrderCompleted::class);
    Event::assertDispatched(EsimReady::class);
});

it('fails fulfillment and emits OrderFailed', function (): void {
    Event::fake([OrderFailed::class]);

    $user = customer('80.00');
    $plan = pricedPlan(5000);
    $order = app(CreateOrder::class)($user, $plan, 'order-fail');
    $paid = app(PayOrderWithWallet::class)($user, $order, $user->wallets()->firstOrFail());
    $fulfilling = app(FulfillOrder::class)($paid);

    $failed = app(FailOrderFulfillment::class)($fulfilling, 'provider timeout');

    expect($failed->status)->toBe(OrderStatus::Failed);
    expect($failed->fulfillment_status)->toBe(FulfillmentStatus::Failed);
    expect($failed->items->first()->esimProfile?->status)->toBe(EsimProfileStatus::Failed);
    Event::assertDispatched(OrderFailed::class);
});
