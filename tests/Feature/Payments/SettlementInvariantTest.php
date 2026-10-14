<?php

declare(strict_types=1);

use App\Actions\Orders\CancelOrder;
use App\Actions\Orders\CaptureReservedOrderPayment;
use App\Actions\Orders\CreateOrder;
use App\Actions\Orders\PayOrderWithWallet;
use App\Actions\Orders\ReserveOrderWithWallet;
use App\Actions\Payments\RefundPayment;
use App\Enums\OrderPaymentStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\SettlementInvariantException;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\PaymentMethod;
use App\Models\PaymentRefund;
use App\Services\Payments\RecordOrderSettlement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

it('records a full wallet payment from allocations', function (): void {
    $user = customer('80.00');
    $plan = pricedPlan(5000);
    $order = app(CreateOrder::class)($user, $plan, 'settle-full');
    $paid = app(PayOrderWithWallet::class)($user, $order, $user->wallets()->firstOrFail());

    expect($paid->paid_minor)->toBe($paid->total_minor)
        ->and($paid->payment_status)->toBe(OrderPaymentStatus::Paid)
        ->and(PaymentAllocation::query()->where('order_id', $paid->id)->where('status', PaymentStatus::Paid)->sum('amount_minor'))->toBe(5000);
});

it('recomputes paid_minor from allocations when a reserved payment is captured', function (): void {
    $user = customer('80.00');
    $plan = pricedPlan(5000);
    $wallet = $user->wallets()->firstOrFail();
    $order = app(CreateOrder::class)($user, $plan, 'settle-capture');
    app(ReserveOrderWithWallet::class)($user, $order, $wallet);

    $captured = app(CaptureReservedOrderPayment::class)($user, $order->fresh());

    expect($captured->paid_minor)->toBe(5000)
        ->and($captured->payment_status)->toBe(OrderPaymentStatus::Paid)
        ->and(PaymentAllocation::query()->where('order_id', $captured->id)->count())->toBe(1);
});

it('keeps paid_minor unchanged on a partial refund and derives the net at read time', function (): void {
    $user = customer('80.00');
    $plan = pricedPlan(5000);
    $order = app(PayOrderWithWallet::class)(
        $user,
        app(CreateOrder::class)($user, $plan, 'settle-partial'),
        $user->wallets()->firstOrFail(),
    );
    $payment = Payment::query()->where('order_id', $order->id)->firstOrFail();
    $method = new PaymentMethod;
    $method->user_id = $user->id;
    $method->type = 'wallet';
    $method->save();
    $payment->payment_method_id = $method->id;
    $payment->save();

    $admin = adminUser();
    app(RefundPayment::class)($admin, $payment, 2000, 'partial', 'settle-partial-refund', Request::create('/'));

    $order->refresh();
    $payment->refresh();

    expect($order->paid_minor)->toBe(5000)
        ->and($payment->refunded_minor)->toBe(2000)
        ->and($order->payment_status)->toBe(OrderPaymentStatus::PartiallyRefunded)
        ->and(app(RecordOrderSettlement::class)->netPaid($order))->toBe(3000)
        ->and(PaymentAllocation::query()->where('order_id', $order->id)->count())->toBe(1);
});

it('keeps the historical allocation when a paid order is cancelled', function (): void {
    $user = customer('80.00');
    $plan = pricedPlan(5000);
    $order = app(PayOrderWithWallet::class)(
        $user,
        app(CreateOrder::class)($user, $plan, 'settle-cancel'),
        $user->wallets()->firstOrFail(),
    );

    $cancelled = app(CancelOrder::class)($user, $order->fresh());

    expect($cancelled->payment_status)->toBe(OrderPaymentStatus::Refunded)
        ->and($cancelled->paid_minor)->toBe(5000)
        ->and(PaymentAllocation::query()->where('order_id', $cancelled->id)->count())->toBe(1)
        ->and(PaymentRefund::query()->where('status', 'completed')->sum('amount_minor'))->toBe(5000)
        ->and(Payment::query()->where('order_id', $cancelled->id)->first()?->refunded_minor)->toBe(5000);
});

it('rolls back an allocation that would exceed the order total', function (): void {
    $user = customer('80.00');
    $plan = pricedPlan(5000);
    $order = app(PayOrderWithWallet::class)(
        $user,
        app(CreateOrder::class)($user, $plan, 'settle-over-allocate'),
        $user->wallets()->firstOrFail(),
    );
    $payment = Payment::query()->where('order_id', $order->id)->firstOrFail();

    expect(fn () => DB::transaction(function () use ($order, $payment): void {
        $lockedOrder = $order->newQuery()->whereKey($order->id)->lockForUpdate()->firstOrFail();
        $lockedPayment = $payment->newQuery()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
        app(RecordOrderSettlement::class)->allocate($lockedOrder, $lockedPayment, 1, 'LYD', 'wallet');
    }))->toThrow(SettlementInvariantException::class);

    expect($order->refresh()->paid_minor)->toBe(5000)
        ->and(PaymentAllocation::query()->where('order_id', $order->id)->count())->toBe(1);
});

it('rolls back a refund that would exceed the payment amount', function (): void {
    $user = customer('80.00');
    $plan = pricedPlan(5000);
    $order = app(PayOrderWithWallet::class)(
        $user,
        app(CreateOrder::class)($user, $plan, 'settle-over-refund'),
        $user->wallets()->firstOrFail(),
    );
    $payment = Payment::query()->where('order_id', $order->id)->firstOrFail();

    expect(fn () => DB::transaction(function () use ($order, $payment): void {
        $lockedOrder = $order->newQuery()->whereKey($order->id)->lockForUpdate()->firstOrFail();
        $lockedPayment = $payment->newQuery()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
        app(RecordOrderSettlement::class)->refund($lockedPayment, $lockedOrder, 5001, 'LYD', 'too much', 'settle-over-refund-key');
    }))->toThrow(SettlementInvariantException::class);

    expect($payment->refresh()->refunded_minor)->toBe(0)
        ->and(PaymentRefund::query()->count())->toBe(0)
        ->and($order->refresh()->paid_minor)->toBe(5000);
});
