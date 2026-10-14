<?php

declare(strict_types=1);

use App\Actions\Orders\ApplyEsimFulfillment;
use App\Actions\Orders\CreateOrder;
use App\Actions\Orders\FailOrderFulfillment;
use App\Actions\Orders\FulfillOrder;
use App\Actions\Orders\ReconcileOrderFulfillment;
use App\Actions\Orders\ReserveOrderWithWallet;
use App\Actions\Providers\ProcessProviderWebhook;
use App\Actions\Wallet\CaptureWalletHold;
use App\Actions\Wallet\DebitWallet;
use App\Actions\Wallet\PlaceWalletHold;
use App\Domain\MoneyMinor;
use App\Enums\FulfillmentStatus;
use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\WalletHoldStatus;
use App\Enums\WebhookEventStatus;
use App\Exceptions\InvalidStateTransitionException;
use App\Integrations\Esim\DTOs\PurchaseRequest;
use App\Integrations\Esim\Manager\ProviderManager;
use App\Jobs\CompleteOrderFulfillmentJob;
use App\Models\EsimUsage;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Provider;
use App\Models\ProviderApiLog;
use App\Models\WalletHold;
use App\Models\WalletLedgerEntry;
use App\Models\WalletTransaction;
use App\Models\WebhookEvent;
use App\Services\Orders\CheckoutEsimOrder;
use App\Services\Orders\CompleteOrderFulfillment;
use App\Services\Providers\AcceptProviderWebhook;
use Illuminate\Support\Facades\Event;

function purchaseLogCount(string $providerId): int
{
    return ProviderApiLog::query()
        ->where('provider_id', $providerId)
        ->where('endpoint', 'purchase')
        ->count();
}

function fakeExternalProfileId(string $idempotencyKey): string
{
    return 'fake-esim-'.substr(md5($idempotencyKey), 0, 8);
}

it('fails checkout on a malformed provider result and restores the wallet', function (): void {
    Event::fake();

    $user = customer('80.00');
    $plan = mappedPricedPlan(5000, 'malformed');
    $wallet = $user->wallets()->firstOrFail();

    $failed = app(CheckoutEsimOrder::class)($user, $plan, $wallet, 'fail-malformed');

    expect($failed->status)->toBe(OrderStatus::Failed);
    expect($failed->payment_status)->toBe(OrderPaymentStatus::Failed);
    expect($failed->fulfillment_status)->toBe(FulfillmentStatus::Failed);
    expect(Payment::query()->where('order_id', $failed->id)->first()?->status)->toBe(PaymentStatus::Cancelled);
    expect(WalletHold::query()->where('reference_id', $failed->id)->first()?->status)->toBe(WalletHoldStatus::Released);
    expect($wallet->refresh()->available_balance_minor)->toBe(8000);
    expect($wallet->held_balance_minor)->toBe(0);
    expect(purchaseLogCount($plan->provider_id))->toBe(1);
});

it('fails a provider outage without capturing funds or buying twice', function (): void {
    Event::fake();

    $user = customer('80.00');
    $plan = mappedPricedPlan(5000, 'unavailable');
    $wallet = $user->wallets()->firstOrFail();

    $failed = app(CheckoutEsimOrder::class)($user, $plan, $wallet, 'fail-unavailable');
    $replayed = app(CheckoutEsimOrder::class)($user, $plan, $wallet->refresh(), 'fail-unavailable');
    app(CompleteOrderFulfillment::class)($user, $failed->fresh());

    expect($replayed->id)->toBe($failed->id);
    expect($failed->refresh()->status)->toBe(OrderStatus::Failed);
    expect($failed->payment_status)->toBe(OrderPaymentStatus::Failed);
    expect(Payment::query()->where('order_id', $failed->id)->where('status', PaymentStatus::Paid)->count())->toBe(0);
    expect($wallet->refresh()->available_balance_minor)->toBe(8000);
    expect($wallet->held_balance_minor)->toBe(0);
    expect(purchaseLogCount($plan->provider_id))->toBe(1);
});

it('recovers capture when fulfillment completed before the worker crashed', function (): void {
    Event::fake();

    $user = customer('80.00');
    $plan = mappedPricedPlan();
    $wallet = $user->wallets()->firstOrFail();
    $order = app(CreateOrder::class)($user, $plan, 'fail-crash-before-capture');
    $reserved = app(ReserveOrderWithWallet::class)($user, $order, $wallet);
    $fulfilling = app(FulfillOrder::class)($reserved);

    $purchase = app(ProviderManager::class)
        ->driver($plan->provider)
        ->purchase(new PurchaseRequest('fake-tr-10gb', $order->idempotency_key ?? $order->id));

    $applied = app(ApplyEsimFulfillment::class)($fulfilling, $purchase->fulfillment);

    expect($applied->status)->toBe(OrderStatus::Completed);
    expect($applied->payment_status)->not->toBe(OrderPaymentStatus::Paid);
    expect(WalletHold::query()->where('reference_id', $applied->id)->first()?->status)->toBe(WalletHoldStatus::Active);
    expect($wallet->refresh()->held_balance_minor)->toBe(5000);

    (new CompleteOrderFulfillmentJob($applied->id))->handle(app(CompleteOrderFulfillment::class));

    $recovered = $applied->refresh();
    expect($recovered->status)->toBe(OrderStatus::Completed);
    expect($recovered->payment_status)->toBe(OrderPaymentStatus::Paid);
    expect(Payment::query()->where('order_id', $recovered->id)->where('status', PaymentStatus::Paid)->count())->toBe(1);
    expect(WalletHold::query()->where('reference_id', $recovered->id)->first()?->status)->toBe(WalletHoldStatus::Captured);
    expect($wallet->refresh()->available_balance_minor)->toBe(3000);
    expect($wallet->held_balance_minor)->toBe(0);
});

it('rolls back capture when ledger posting fails mid-transaction', function (): void {
    $user = customer('80.00');
    $wallet = $user->wallets()->firstOrFail();
    $hold = app(PlaceWalletHold::class)($user, $wallet, MoneyMinor::of(3000, 'LYD'), 'fail-ledger-hold');

    $listener = function (): never {
        throw new LogicException('injected ledger crash');
    };
    WalletLedgerEntry::creating($listener);

    try {
        expect(fn () => app(CaptureWalletHold::class)($user, $hold, 'fail-ledger-capture'))
            ->toThrow(LogicException::class);
    } finally {
        WalletLedgerEntry::getEventDispatcher()?->forget('eloquent.creating: '.WalletLedgerEntry::class);
    }

    expect($hold->refresh()->status)->toBe(WalletHoldStatus::Active);
    expect($wallet->refresh()->available_balance_minor)->toBe(5000);
    expect($wallet->held_balance_minor)->toBe(3000);
    expect(WalletTransaction::query()->where('idempotency_key', 'fail-ledger-capture')->count())->toBe(0);
    expect(WalletLedgerEntry::query()->count())->toBe(0);
});

it('rolls back a wallet debit when persistence crashes inside the transaction', function (): void {
    $user = customer('80.00');
    $wallet = $user->wallets()->firstOrFail();

    $listener = function (): never {
        throw new RuntimeException('injected debit crash');
    };
    WalletTransaction::creating($listener);

    try {
        expect(fn () => app(DebitWallet::class)($user, $wallet, MoneyMinor::of(2000, 'LYD'), 'crash', 'fail-debit-crash'))
            ->toThrow(RuntimeException::class);
    } finally {
        WalletTransaction::getEventDispatcher()?->forget('eloquent.creating: '.WalletTransaction::class);
    }

    expect($wallet->refresh()->available_balance_minor)->toBe(8000);
    expect(WalletTransaction::query()->where('wallet_id', $wallet->id)->count())->toBe(0);
});

it('does not unwind a captured payment when a later fail is attempted', function (): void {
    Event::fake();

    $user = customer('80.00');
    $plan = mappedPricedPlan();
    $wallet = $user->wallets()->firstOrFail();
    $completed = app(CheckoutEsimOrder::class)($user, $plan, $wallet, 'fail-after-complete');

    expect(fn () => app(FailOrderFulfillment::class)($completed, 'late provider failure'))
        ->toThrow(InvalidStateTransitionException::class);

    expect($completed->refresh()->status)->toBe(OrderStatus::Completed);
    expect($completed->payment_status)->toBe(OrderPaymentStatus::Paid);
    expect($wallet->refresh()->available_balance_minor)->toBe(3000);
    expect($wallet->held_balance_minor)->toBe(0);
});

it('keeps a failed checkout failed under the same idempotency key after retry', function (): void {
    Event::fake();

    $user = customer('80.00');
    $plan = mappedPricedPlan(5000, 'rejected');
    $wallet = $user->wallets()->firstOrFail();
    $checkout = app(CheckoutEsimOrder::class);

    $failed = $checkout($user, $plan, $wallet, 'fail-rejected-idem');
    $replayed = $checkout($user, $plan, $wallet->refresh(), 'fail-rejected-idem');
    (new CompleteOrderFulfillmentJob($failed->id))->handle(app(CompleteOrderFulfillment::class));

    expect($replayed->id)->toBe($failed->id);
    expect($replayed->status)->toBe(OrderStatus::Failed);
    expect(Order::query()->count())->toBe(1);
    expect(purchaseLogCount($plan->provider_id))->toBe(1);
    expect($wallet->refresh()->available_balance_minor)->toBe(8000);
});

it('recovers a webhook after the eSIM profile exists without duplicating usage', function (): void {
    Event::fake();
    config(['esim.webhook_max_attempts' => 8]);

    $key = 'fail-wh-before-profile';
    $provider = Provider::factory()->fake()->create();
    $payload = [
        'travora_fixture' => 'usage',
        'external_profile_id' => fakeExternalProfileId($key),
        'used_mb' => 40,
        'remaining_mb' => 984,
    ];

    $early = app(AcceptProviderWebhook::class)($provider, 'esim.usage', $payload, 'fail-wh-early');
    expect($early->fresh()->status)->toBe(WebhookEventStatus::Pending);
    expect($early->fresh()->attempt_count)->toBeGreaterThan(0);
    expect(EsimUsage::query()->count())->toBe(0);

    $user = customer('80.00');
    $plan = mappedPlanOnProvider($provider, 5000, 'fake-tr-10gb');
    $order = app(CheckoutEsimOrder::class)($user, $plan, $user->wallets()->firstOrFail(), $key);
    $profile = $order->items->first()->esimProfile;

    $processed = app(ProcessProviderWebhook::class)($early->fresh());

    expect($order->status)->toBe(OrderStatus::Completed);
    expect($processed->status)->toBe(WebhookEventStatus::Processed);
    expect(WebhookEvent::query()->where('provider_id', $provider->id)->count())->toBe(1);
    expect(EsimUsage::query()->where('esim_profile_id', $profile->id)->count())->toBe(1);
    expect($profile->refresh()->data_remaining_mb)->toBe(984);
});

it('does not purchase again when a timed-out worker is retried before the deadline', function (): void {
    Event::fake();

    $user = customer('80.00');
    $plan = mappedPricedPlan(5000, 'timeout_missing');
    $wallet = $user->wallets()->firstOrFail();

    $pending = app(CheckoutEsimOrder::class)($user, $plan, $wallet, 'fail-timeout-job-retry');
    (new CompleteOrderFulfillmentJob($pending->id))->handle(app(CompleteOrderFulfillment::class));
    (new CompleteOrderFulfillmentJob($pending->id))->handle(app(CompleteOrderFulfillment::class));

    expect($pending->refresh()->status)->toBe(OrderStatus::Processing);
    expect($pending->payment_status)->toBe(OrderPaymentStatus::Pending);
    expect(purchaseLogCount($plan->provider_id))->toBe(1);
    expect($wallet->refresh()->held_balance_minor)->toBe(5000);

    $reconciled = app(ReconcileOrderFulfillment::class)($pending->fresh(), true);

    expect($reconciled->status)->toBe(OrderStatus::Processing);
    expect($reconciled->metadata['reconciliation_status'])->toBe('manual_review_required');
    expect(purchaseLogCount($plan->provider_id))->toBe(1);
    expect($wallet->refresh()->available_balance_minor)->toBe(3000);
    expect($wallet->held_balance_minor)->toBe(5000);
});
