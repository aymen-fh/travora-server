<?php

declare(strict_types=1);

use App\Actions\Catalog\SetEsimPlanPrice;
use App\Actions\Orders\CreateOrder;
use App\Actions\Orders\FailOrderFulfillment;
use App\Actions\Orders\FulfillOrder;
use App\Actions\Orders\PayOrderWithWallet;
use App\Actions\Orders\ReconcileOrderFulfillment;
use App\Domain\MoneyMinor;
use App\Enums\EsimProfileStatus;
use App\Enums\FulfillmentStatus;
use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\WalletHoldStatus;
use App\Exceptions\ForbiddenOperationException;
use App\Jobs\CompleteOrderFulfillmentJob;
use App\Models\Order;
use App\Models\Payment;
use App\Models\WalletHold;
use App\Models\WalletTransaction;
use App\Services\Orders\CheckoutEsimOrder;
use App\Services\Orders\CompleteOrderFulfillment;
use Illuminate\Support\Facades\Event;

it('freezes the catalog quote into the order item so later price changes do not rewrite it', function (): void {
    $user = customer('200.00');
    $plan = pricedPlan(5000);

    $order = app(CreateOrder::class)($user, $plan, 'commerce-price-freeze', 2);
    $item = $order->items->first();

    expect($order->currency)->toBe('LYD');
    expect($order->subtotal_minor)->toBe(10000);
    expect($order->discount_minor)->toBe(0);
    expect($order->tax_minor)->toBe(0);
    expect($order->total_minor)->toBe(10000);
    expect($item?->reference_id)->toBe($plan->id);
    expect($item?->quantity)->toBe(2);
    expect($item?->unit_price_minor)->toBe(5000);
    expect($item?->discount_minor)->toBe(0);
    expect($item?->tax_minor)->toBe(0);
    expect($item?->total_minor)->toBe(10000);
    expect($item?->metadata['quoted_price_minor'])->toBe(5000);

    app(SetEsimPlanPrice::class)($plan, MoneyMinor::of(9000, 'LYD'));

    $replayed = app(CreateOrder::class)($user, $plan, 'commerce-price-freeze', 2);
    $paid = app(PayOrderWithWallet::class)($user, $replayed, $user->wallets()->firstOrFail());

    expect($replayed->id)->toBe($order->id);
    expect($replayed->total_minor)->toBe(10000);
    expect($replayed->items->first()?->unit_price_minor)->toBe(5000);
    expect($paid->paid_minor)->toBe(10000);
    expect($paid->payment_status)->toBe(OrderPaymentStatus::Paid);
});

it('checks out through hold, provider purchase, then capture without mixing payment into fulfillment', function (): void {
    Event::fake();

    $user = customer('80.00');
    $plan = mappedPricedPlan();
    $wallet = $user->wallets()->firstOrFail();
    $checkout = app(CheckoutEsimOrder::class);

    $completed = $checkout($user, $plan, $wallet, 'commerce-checkout-ok');
    $replayed = $checkout($user, $plan, $wallet, 'commerce-checkout-ok');

    expect($replayed->id)->toBe($completed->id);
    expect($completed->status)->toBe(OrderStatus::Completed);
    expect($completed->payment_status)->toBe(OrderPaymentStatus::Paid);
    expect($completed->fulfillment_status)->toBe(FulfillmentStatus::Completed);
    expect($completed->items->first()?->esimProfile?->status)->toBe(EsimProfileStatus::Ready);
    expect(Order::query()->count())->toBe(1);
    expect(Payment::query()->where('order_id', $completed->id)->count())->toBe(1);
    expect(WalletHold::query()->where('reference_id', $completed->id)->count())->toBe(1);
    expect(WalletHold::query()->where('reference_id', $completed->id)->first()?->status)->toBe(WalletHoldStatus::Captured);
    expect($wallet->refresh()->available_balance_minor)->toBe(3000);
    expect($wallet->held_balance_minor)->toBe(0);
    expect(WalletTransaction::query()->where('wallet_id', $wallet->id)->count())->toBe(2);
});

it('releases the wallet hold when provider fulfillment is rejected', function (): void {
    Event::fake();

    $user = customer('80.00');
    $plan = mappedPricedPlan(5000, 'rejected');
    $wallet = $user->wallets()->firstOrFail();

    $failed = app(CheckoutEsimOrder::class)($user, $plan, $wallet, 'commerce-rejected');

    expect($failed->status)->toBe(OrderStatus::Failed);
    expect($failed->payment_status)->toBe(OrderPaymentStatus::Failed);
    expect($failed->fulfillment_status)->toBe(FulfillmentStatus::Failed);
    expect(Payment::query()->where('order_id', $failed->id)->first()?->status)->toBe(PaymentStatus::Cancelled);
    expect(WalletHold::query()->where('reference_id', $failed->id)->first()?->status)->toBe(WalletHoldStatus::Released);
    expect($wallet->refresh()->available_balance_minor)->toBe(8000);
    expect($wallet->held_balance_minor)->toBe(0);
});

it('keeps a captured payment when fulfillment fails later', function (): void {
    Event::fake();

    $user = customer('80.00');
    $plan = pricedPlan(5000);
    $wallet = $user->wallets()->firstOrFail();
    $order = app(CreateOrder::class)($user, $plan, 'commerce-paid-then-fail');
    $paid = app(PayOrderWithWallet::class)($user, $order, $wallet);
    $fulfilling = app(FulfillOrder::class)($paid);
    $failed = app(FailOrderFulfillment::class)($fulfilling, 'provider rejected after capture');

    expect($failed->payment_status)->toBe(OrderPaymentStatus::Paid);
    expect($failed->fulfillment_status)->toBe(FulfillmentStatus::Failed);
    expect($failed->status)->toBe(OrderStatus::Failed);
    expect($wallet->refresh()->available_balance_minor)->toBe(3000);
    expect($wallet->held_balance_minor)->toBe(0);
});

it('reconciles a late provider purchase without buying twice', function (): void {
    Event::fake();

    $user = customer('80.00');
    $plan = mappedPricedPlan(5000, 'timeout');
    $wallet = $user->wallets()->firstOrFail();

    $completed = app(CheckoutEsimOrder::class)($user, $plan, $wallet, 'commerce-timeout');

    expect($completed->status)->toBe(OrderStatus::Completed);
    expect($completed->payment_status)->toBe(OrderPaymentStatus::Paid);
    expect($completed->items->first()?->esimProfile?->external_profile_id)->toStartWith('fake-esim-');
    expect(Order::query()->count())->toBe(1);
});

it('does not purchase again after a timeout with no remembered result', function (): void {
    Event::fake();

    $user = customer('80.00');
    $plan = mappedPricedPlan(5000, 'timeout_missing');
    $wallet = $user->wallets()->firstOrFail();
    $checkout = app(CheckoutEsimOrder::class);

    $pending = $checkout($user, $plan, $wallet, 'commerce-timeout-missing');
    $replayed = $checkout($user, $plan, $wallet, 'commerce-timeout-missing');

    expect($pending->id)->toBe($replayed->id);
    expect($replayed->status)->toBe(OrderStatus::Processing);
    expect($replayed->payment_status)->toBe(OrderPaymentStatus::Pending);
    expect($replayed->fulfillment_status)->toBe(FulfillmentStatus::Processing);
    expect(WalletHold::query()->where('reference_id', $replayed->id)->first()?->status)->toBe(WalletHoldStatus::Active);
    expect($wallet->refresh()->available_balance_minor)->toBe(3000);
    expect($wallet->held_balance_minor)->toBe(5000);
    expect(Order::query()->count())->toBe(1);

    $reconciled = app(ReconcileOrderFulfillment::class)($replayed, true);

    expect($reconciled->status)->toBe(OrderStatus::Processing);
    expect($reconciled->fulfillment_status)->toBe(FulfillmentStatus::Processing);
    expect($reconciled->metadata['reconciliation_status'])->toBe('manual_review_required');
    expect(WalletHold::query()->where('reference_id', $reconciled->id)->first()?->status)->toBe(WalletHoldStatus::Active);
    expect($wallet->refresh()->available_balance_minor)->toBe(3000);
    expect($wallet->refresh()->held_balance_minor)->toBe(5000);
});

it('is idempotent across checkout and worker retries', function (): void {
    Event::fake();

    $user = customer('80.00');
    $plan = mappedPricedPlan();
    $wallet = $user->wallets()->firstOrFail();
    $checkout = app(CheckoutEsimOrder::class);

    $first = $checkout($user, $plan, $wallet, 'commerce-worker-retry');
    $second = $checkout($user, $plan, $wallet, 'commerce-worker-retry');
    $worker = app(CompleteOrderFulfillment::class)($user, $second);
    (new CompleteOrderFulfillmentJob($first->id))->handle(app(CompleteOrderFulfillment::class));

    expect($second->id)->toBe($first->id);
    expect($worker->id)->toBe($first->id);
    expect(Order::query()->count())->toBe(1);
    expect(Payment::query()->count())->toBe(1);
    expect($first->items()->with('esimProfile')->get()->filter(fn ($item) => $item->esimProfile !== null)->count())->toBe(1);
    expect($wallet->refresh()->available_balance_minor)->toBe(3000);
});

it('refuses fulfillment when neither payment nor a wallet hold exists', function (): void {
    $user = customer('80.00');
    $plan = pricedPlan(5000);
    $order = app(CreateOrder::class)($user, $plan, 'commerce-unpaid-fulfill');

    app(FulfillOrder::class)($order);
})->throws(ForbiddenOperationException::class);
