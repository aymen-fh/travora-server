<?php

declare(strict_types=1);

use App\Actions\Orders\CancelOrder;
use App\Actions\Orders\CaptureReservedOrderPayment;
use App\Actions\Orders\CreateOrder;
use App\Actions\Orders\PayOrderWithWallet;
use App\Actions\Orders\ReserveOrderWithWallet;
use App\Actions\Providers\ProcessProviderWebhook;
use App\Actions\Wallet\CreditWallet;
use App\Actions\Wallet\DebitWallet;
use App\Domain\MoneyMinor;
use App\Enums\FulfillmentStatus;
use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\WalletHoldStatus;
use App\Enums\WalletTransactionType;
use App\Exceptions\ForbiddenOperationException;
use App\Exceptions\InsufficientBalanceException;
use App\Jobs\CompleteOrderFulfillmentJob;
use App\Jobs\ProcessProviderWebhookJob;
use App\Models\EsimUsage;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentRefund;
use App\Models\ProviderApiLog;
use App\Models\WalletHold;
use App\Models\WalletLedgerEntry;
use App\Models\WalletTransaction;
use App\Models\WebhookEvent;
use App\Services\Orders\CheckoutEsimOrder;
use App\Services\Providers\AcceptProviderWebhook;
use Illuminate\Support\Facades\Event;

function snapshotOrder(Order $order): Order
{
    return Order::query()->findOrFail($order->id);
}

function assertWalletSolvent(string $walletId): void
{
    $wallet = App\Models\Wallet::query()->findOrFail($walletId);

    expect($wallet->available_balance_minor)->toBeGreaterThanOrEqual(0);
    expect($wallet->held_balance_minor)->toBeGreaterThanOrEqual(0);
}

function assertLedgerBalancedForWallet(string $walletId): void
{
    $transactionIds = WalletTransaction::query()->where('wallet_id', $walletId)->pluck('id');
    $debit = (int) WalletLedgerEntry::query()->whereIn('wallet_transaction_id', $transactionIds)->sum('debit_minor');
    $credit = (int) WalletLedgerEntry::query()->whereIn('wallet_transaction_id', $transactionIds)->sum('credit_minor');

    expect($debit)->toBe($credit);
}

it('replays checkout with the same idempotency key without duplicating money movement', function (): void {
    Event::fake();

    $user = customer('80.00');
    $plan = mappedPricedPlan();
    $wallet = $user->wallets()->firstOrFail();
    $checkout = app(CheckoutEsimOrder::class);

    $first = $checkout($user, $plan, $wallet, 'conc-same-key');
    $second = $checkout($user, $plan, $wallet, 'conc-same-key');

    expect($second->id)->toBe($first->id);
    expect(Order::query()->where('user_id', $user->id)->count())->toBe(1);
    expect(Payment::query()->where('order_id', $first->id)->count())->toBe(1);
    expect(WalletHold::query()->where('reference_id', $first->id)->count())->toBe(1);
    expect($wallet->refresh()->available_balance_minor)->toBe(3000);
    expect($wallet->held_balance_minor)->toBe(0);
    assertWalletSolvent($wallet->id);
    assertLedgerBalancedForWallet($wallet->id);
});

it('does not overdraw the wallet when two checkouts use different idempotency keys', function (): void {
    Event::fake();

    $user = customer('80.00');
    $plan = mappedPricedPlan(5000);
    $wallet = $user->wallets()->firstOrFail();
    $checkout = app(CheckoutEsimOrder::class);

    $first = $checkout($user, $plan, $wallet, 'conc-key-a');

    expect($first->status)->toBe(OrderStatus::Completed);

    $secondFailed = false;
    try {
        $checkout($user, $plan, $wallet->refresh(), 'conc-key-b');
    } catch (InsufficientBalanceException) {
        $secondFailed = true;
    }

    expect($secondFailed)->toBeTrue();
    expect(Payment::query()->where('user_id', $user->id)->where('status', PaymentStatus::Paid)->count())->toBe(1);
    expect(Order::query()->where('user_id', $user->id)->where('status', OrderStatus::Completed)->count())->toBe(1);
    expect($wallet->refresh()->available_balance_minor)->toBe(3000);
    expect($wallet->held_balance_minor)->toBe(0);
    assertWalletSolvent($wallet->id);
    assertLedgerBalancedForWallet($wallet->id);
});

it('keeps a single debit when the same wallet key is retried', function (): void {
    $user = customer('80.00');
    $wallet = $user->wallets()->firstOrFail();
    $debit = app(DebitWallet::class);

    $first = $debit($user, $wallet, MoneyMinor::of(2000, 'LYD'), 'purchase', 'conc-debit-same');
    $second = $debit($user, $wallet->refresh(), MoneyMinor::of(2000, 'LYD'), 'purchase', 'conc-debit-same');

    expect($second->id)->toBe($first->id);
    expect(WalletTransaction::query()->where('wallet_id', $wallet->id)->count())->toBe(1);
    expect($wallet->refresh()->available_balance_minor)->toBe(6000);
    assertLedgerBalancedForWallet($wallet->id);
});

it('rejects a second debit that would make the wallet negative', function (): void {
    $user = customer('80.00');
    $wallet = $user->wallets()->firstOrFail();
    $debit = app(DebitWallet::class);

    $debit($user, $wallet, MoneyMinor::of(5000, 'LYD'), 'first', 'conc-debit-a');

    expect(fn () => $debit($user, $wallet->refresh(), MoneyMinor::of(5000, 'LYD'), 'second', 'conc-debit-b'))
        ->toThrow(InsufficientBalanceException::class);

    expect($wallet->refresh()->available_balance_minor)->toBe(3000);
    expect(WalletTransaction::query()->where('wallet_id', $wallet->id)->count())->toBe(1);
    assertWalletSolvent($wallet->id);
    assertLedgerBalancedForWallet($wallet->id);
});

it('applies a credit and debit on the same wallet without losing ledger balance', function (): void {
    $user = customer('50.00');
    $wallet = $user->wallets()->firstOrFail();

    app(CreditWallet::class)(
        $user,
        $wallet,
        MoneyMinor::of(2000, 'LYD'),
        WalletTransactionType::Topup,
        'topup',
        'conc-credit-1',
    );

    app(DebitWallet::class)($user, $wallet->refresh(), MoneyMinor::of(2000, 'LYD'), 'spend', 'conc-debit-after-credit');

    expect($wallet->refresh()->available_balance_minor)->toBe(5000);
    expect(WalletTransaction::query()->where('wallet_id', $wallet->id)->count())->toBe(2);
    assertLedgerBalancedForWallet($wallet->id);
});

it('processes the same webhook event only once when the worker is invoked twice', function (): void {
    Event::fake();

    $user = customer('80.00');
    $plan = mappedPricedPlan();
    $order = app(CheckoutEsimOrder::class)($user, $plan, $user->wallets()->firstOrFail(), 'conc-wh-checkout');
    $profile = $order->items->first()->esimProfile;
    $provider = $plan->provider;
    $payload = [
        'travora_fixture' => 'usage',
        'external_profile_id' => $profile->external_profile_id,
        'used_mb' => 150,
        'remaining_mb' => 874,
    ];

    $event = app(AcceptProviderWebhook::class)($provider, 'esim.usage', $payload, 'conc-wh-evt');
    $duplicate = app(AcceptProviderWebhook::class)($provider, 'esim.usage', $payload, 'conc-wh-evt');

    (new ProcessProviderWebhookJob($event->id))->handle(app(ProcessProviderWebhook::class));
    (new ProcessProviderWebhookJob($duplicate->id))->handle(app(ProcessProviderWebhook::class));

    expect($duplicate->id)->toBe($event->id);
    expect(WebhookEvent::query()->where('provider_id', $provider->id)->count())->toBe(1);
    expect(EsimUsage::query()->where('esim_profile_id', $profile->id)->count())->toBe(1);
    expect($profile->refresh()->data_remaining_mb)->toBe(874);
});

it('does not purchase again when a timeout checkout is retried with the same key', function (): void {
    Event::fake();

    $user = customer('80.00');
    $plan = mappedPricedPlan(5000, 'timeout');
    $wallet = $user->wallets()->firstOrFail();
    $checkout = app(CheckoutEsimOrder::class);

    $first = $checkout($user, $plan, $wallet, 'conc-timeout-retry');
    $second = $checkout($user, $plan, $wallet->refresh(), 'conc-timeout-retry');

    expect($second->id)->toBe($first->id);
    expect($second->status)->toBe(OrderStatus::Completed);
    expect($second->payment_status)->toBe(OrderPaymentStatus::Paid);
    expect(Order::query()->count())->toBe(1);
    expect(Payment::query()->where('order_id', $first->id)->count())->toBe(1);
    expect(ProviderApiLog::query()->where('provider_id', $plan->provider_id)->where('endpoint', 'purchase')->count())->toBe(1);
    expect(ProviderApiLog::query()->where('provider_id', $plan->provider_id)->where('endpoint', 'findPurchase')->count())->toBeGreaterThanOrEqual(1);
    expect($wallet->refresh()->available_balance_minor)->toBe(3000);
    assertLedgerBalancedForWallet($wallet->id);
});

it('is stable when CompleteOrderFulfillmentJob handle is invoked twice', function (): void {
    Event::fake();

    $user = customer('80.00');
    $plan = mappedPricedPlan();
    $wallet = $user->wallets()->firstOrFail();
    $order = app(CheckoutEsimOrder::class)($user, $plan, $wallet, 'conc-job-double');

    (new CompleteOrderFulfillmentJob($order->id))->handle(app(App\Services\Orders\CompleteOrderFulfillment::class));
    (new CompleteOrderFulfillmentJob($order->id))->handle(app(App\Services\Orders\CompleteOrderFulfillment::class));

    expect(Order::query()->count())->toBe(1);
    expect(Payment::query()->where('order_id', $order->id)->count())->toBe(1);
    expect($order->items()->with('esimProfile')->get()->filter(fn ($item) => $item->esimProfile !== null)->count())->toBe(1);
    expect($wallet->refresh()->available_balance_minor)->toBe(3000);
    expect($wallet->held_balance_minor)->toBe(0);
    assertLedgerBalancedForWallet($wallet->id);
});

it('refunds a paid order only once when cancel is retried with a separate model instance', function (): void {
    $user = customer('80.00');
    $plan = pricedPlan(5000);
    $wallet = $user->wallets()->firstOrFail();
    $order = app(CreateOrder::class)($user, $plan, 'conc-cancel-paid-race');
    $paid = app(PayOrderWithWallet::class)($user, $order, $wallet);
    $retry = snapshotOrder($paid);

    $first = app(CancelOrder::class)($user, $paid);
    $second = app(CancelOrder::class)($user, $retry);

    expect($first->status)->toBe(OrderStatus::Cancelled);
    expect($second->status)->toBe(OrderStatus::Cancelled);
    expect($second->payment_status)->toBe(OrderPaymentStatus::Refunded);
    expect(PaymentRefund::query()->count())->toBe(1);
    expect(Payment::query()->where('order_id', $paid->id)->where('status', PaymentStatus::Refunded)->count())->toBe(1);
    expect($wallet->refresh()->available_balance_minor)->toBe(8000);
    expect($wallet->held_balance_minor)->toBe(0);
    assertLedgerBalancedForWallet($wallet->id);
});

it('cannot cancel a completed checkout using a stale pending order snapshot', function (): void {
    Event::fake();

    $user = customer('80.00');
    $plan = mappedPricedPlan();
    $wallet = $user->wallets()->firstOrFail();
    $pending = app(CreateOrder::class)($user, $plan, 'conc-stale-complete');
    $stale = snapshotOrder($pending);

    $completed = app(CheckoutEsimOrder::class)($user, $plan, $wallet, 'conc-stale-complete');
    expect($completed->status)->toBe(OrderStatus::Completed);

    expect(fn () => app(CancelOrder::class)($user, $stale))
        ->toThrow(ForbiddenOperationException::class);

    $fresh = $completed->refresh();
    expect($fresh->status)->toBe(OrderStatus::Completed);
    expect($fresh->payment_status)->toBe(OrderPaymentStatus::Paid);
    expect(PaymentRefund::query()->count())->toBe(0);
    expect($wallet->refresh()->available_balance_minor)->toBe(3000);
    assertLedgerBalancedForWallet($wallet->id);
});

it('refunds after capture when cancel races with a stale reserved snapshot', function (): void {
    $user = customer('80.00');
    $plan = pricedPlan(5000);
    $wallet = $user->wallets()->firstOrFail();
    $order = app(CreateOrder::class)($user, $plan, 'conc-capture-then-cancel');
    $reserved = app(ReserveOrderWithWallet::class)($user, $order, $wallet);
    $stale = snapshotOrder($reserved);

    $captured = app(CaptureReservedOrderPayment::class)($user, $reserved->fresh(['items', 'payments']));
    expect($captured->payment_status)->toBe(OrderPaymentStatus::Paid);

    $cancelled = app(CancelOrder::class)($user, $stale);

    expect($cancelled->status)->toBe(OrderStatus::Cancelled);
    expect($cancelled->payment_status)->toBe(OrderPaymentStatus::Refunded);
    expect(PaymentRefund::query()->whereHas('payment', fn ($query) => $query->where('order_id', $order->id))->count())->toBe(1);
    expect($wallet->refresh()->available_balance_minor)->toBe(8000);
    expect($wallet->held_balance_minor)->toBe(0);
    expect(WalletHold::query()->where('reference_id', $order->id)->first()?->status)->toBe(WalletHoldStatus::Captured);
    assertLedgerBalancedForWallet($wallet->id);
});

it('does not recapture funds after the reserved order was cancelled', function (): void {
    $user = customer('80.00');
    $plan = pricedPlan(5000);
    $wallet = $user->wallets()->firstOrFail();
    $order = app(CreateOrder::class)($user, $plan, 'conc-cancel-then-capture');
    $reserved = app(ReserveOrderWithWallet::class)($user, $order, $wallet);
    $staleCapture = snapshotOrder($reserved);

    $cancelled = app(CancelOrder::class)($user, $reserved->fresh());
    expect($cancelled->status)->toBe(OrderStatus::Cancelled);

    expect(fn () => app(CaptureReservedOrderPayment::class)($user, $staleCapture))
        ->toThrow(ForbiddenOperationException::class);

    expect($wallet->refresh()->available_balance_minor)->toBe(8000);
    expect($wallet->held_balance_minor)->toBe(0);
    expect(Payment::query()->where('order_id', $order->id)->where('status', PaymentStatus::Paid)->count())->toBe(0);
    expect(WalletHold::query()->where('reference_id', $order->id)->first()?->status)->toBe(WalletHoldStatus::Released);
});

it('captures a reserved payment only once across two capture calls', function (): void {
    $user = customer('80.00');
    $plan = pricedPlan(5000);
    $wallet = $user->wallets()->firstOrFail();
    $order = app(CreateOrder::class)($user, $plan, 'conc-double-capture');
    $reserved = app(ReserveOrderWithWallet::class)($user, $order, $wallet);
    $again = snapshotOrder($reserved);

    $first = app(CaptureReservedOrderPayment::class)($user, $reserved);
    $second = app(CaptureReservedOrderPayment::class)($user, $again);

    expect($first->payment_status)->toBe(OrderPaymentStatus::Paid);
    expect($second->payment_status)->toBe(OrderPaymentStatus::Paid);
    expect(Payment::query()->where('order_id', $order->id)->count())->toBe(1);
    expect(WalletTransaction::query()->where('wallet_id', $wallet->id)->where('type', WalletTransactionType::Payment->value)->count())->toBe(1);
    expect($wallet->refresh()->available_balance_minor)->toBe(3000);
    expect($wallet->held_balance_minor)->toBe(0);
    assertLedgerBalancedForWallet($wallet->id);
});

it('safely serializes race when admin cancel executes while fulfillment worker holds stale processing snapshot', function (): void {
    Event::fake();

    $user = customer('80.00');
    $plan = mappedPricedPlan(5000, 'timeout_missing');
    $wallet = $user->wallets()->firstOrFail();
    $order = app(CheckoutEsimOrder::class)($user, $plan, $wallet, 'conc-cancel-race-worker');

    $staleWorkerOrder = snapshotOrder($order);

    // Admin cancel wins the race and commits
    $cancelled = app(CancelOrder::class)($user, $order->fresh());
    expect($cancelled->status)->toBe(OrderStatus::Cancelled);
    expect(WalletHold::query()->where('reference_id', $order->id)->firstOrFail()->status)->toBe(WalletHoldStatus::Released);
    expect($wallet->refresh()->available_balance_minor)->toBe(8000);

    // Simulate provider delivered purchase during the race
    \Illuminate\Support\Facades\Cache::put('fake-provider-purchase:conc-cancel-race-worker', [
        'order_id' => 'fake-order-conc-race',
        'profile_id' => 'fake-esim-conc-race',
        'plan_id' => 'fake-tr-10gb',
    ], 3600);

    // Fulfillment worker runs with stale processing snapshot
    $retried = app(\App\Services\Orders\CompleteOrderFulfillment::class)($user, $staleWorkerOrder);

    // Order MUST remain Cancelled, hold MUST remain Released, customer funds NEVER recaptured
    expect($retried->status)->toBe(OrderStatus::Cancelled);
    expect($retried->fulfillment_status)->toBe(FulfillmentStatus::Cancelled);
    expect(WalletHold::query()->where('reference_id', $order->id)->firstOrFail()->status)->toBe(WalletHoldStatus::Released);
    expect($wallet->refresh()->available_balance_minor)->toBe(8000);

    // Financial discrepancy is safely captured
    $meta = $retried->fresh()->metadata;
    expect($meta['reconciliation_status'])->toBe('financial_discrepancy_detected');
    expect($meta['unreconciled_provider_fulfillment']['provider_order_no'])->toBe('fake-order-conc-race');
    assertLedgerBalancedForWallet($wallet->id);
});

it('rejects cancellation when fulfillment worker wins the race and completes order first', function (): void {
    Event::fake();

    $user = customer('80.00');
    $plan = mappedPricedPlan(5000);
    $wallet = $user->wallets()->firstOrFail();
    $order = app(CreateOrder::class)($user, $plan, 'conc-fulfill-wins');
    $reserved = app(ReserveOrderWithWallet::class)($user, $order, $wallet);
    $staleAdminCancel = snapshotOrder($reserved);

    // Worker wins the race and completes fulfillment
    $completed = app(CheckoutEsimOrder::class)($user, $plan, $wallet, 'conc-fulfill-wins');
    expect($completed->status)->toBe(OrderStatus::Completed);
    expect($completed->fulfillment_status)->toBe(FulfillmentStatus::Completed);
    expect(WalletHold::query()->where('reference_id', $order->id)->firstOrFail()->status)->toBe(WalletHoldStatus::Captured);

    // Admin cancel arrives with stale processing snapshot
    expect(fn () => app(CancelOrder::class)($user, $staleAdminCancel))
        ->toThrow(ForbiddenOperationException::class);

    // Invariants preserved: order is Completed, hold is Captured, no money leaked
    $fresh = $order->fresh();
    expect($fresh->status)->toBe(OrderStatus::Completed);
    expect($fresh->fulfillment_status)->toBe(FulfillmentStatus::Completed);
    expect(WalletHold::query()->where('reference_id', $order->id)->firstOrFail()->status)->toBe(WalletHoldStatus::Captured);
    expect($wallet->refresh()->available_balance_minor)->toBe(3000);
    assertLedgerBalancedForWallet($wallet->id);
});
