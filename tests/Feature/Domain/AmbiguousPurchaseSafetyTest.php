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
use App\Domain\EsimFulfillmentResult;
use App\Enums\FulfillmentStatus;
use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\WalletHoldStatus;
use App\Enums\WebhookEventStatus;
use App\Integrations\Esim\DTOs\PurchaseRequest;
use App\Integrations\Esim\Manager\ProviderManager;
use App\Jobs\CompleteOrderFulfillmentJob;
use App\Jobs\ReconcileOrderFulfillmentJob;
use App\Jobs\SweepStaleFulfillmentsJob;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Provider;
use App\Models\ProviderApiLog;
use App\Models\WalletHold;
use App\Models\WalletLedgerEntry;
use App\Models\WebhookEvent;
use App\Services\Orders\CheckoutEsimOrder;
use App\Services\Orders\CompleteOrderFulfillment;
use App\Services\Providers\AcceptProviderWebhook;
use Illuminate\Support\Facades\Event;

function countProviderPurchases(string $providerId): int
{
    return ProviderApiLog::query()
        ->where('provider_id', $providerId)
        ->where('endpoint', 'purchase')
        ->count();
}

function testFulfillmentResult(string $externalProfileId): EsimFulfillmentResult
{
    return new EsimFulfillmentResult(
        externalProfileId: $externalProfileId,
        iccid: '8943108170000775671',
        matchingId: 'MATCHING_CODE',
        smdpAddress: 'rsp.example.com',
        activationCode: 'LPA:1$rsp.example.com$MATCHING_CODE',
        confirmationCode: null,
        dataTotalMb: 10240,
        dataRemainingMb: 10240,
    );
}

beforeEach(function (): void {
    config(['esim.retain_ambiguous_holds' => true]);
});

// Scenario 1: Provider purchase succeeds but the HTTP response is lost.
it('Scenario 1: retains wallet hold as active when provider purchase succeeds but response is lost', function (): void {
    Event::fake();

    $user = customer('80.00');
    $plan = mappedPricedPlan(5000, 'timeout_missing');
    $wallet = $user->wallets()->firstOrFail();

    $order = app(CheckoutEsimOrder::class)($user, $plan, $wallet, 'safety-scen-1');

    expect($order->status)->toBe(OrderStatus::Processing);
    expect($order->fulfillment_status)->toBe(FulfillmentStatus::Processing);
    expect($order->payment_status)->toBe(OrderPaymentStatus::Pending);

    $hold = WalletHold::query()->where('reference_id', $order->id)->firstOrFail();
    expect($hold->status)->toBe(WalletHoldStatus::Active);
    expect($wallet->refresh()->available_balance_minor)->toBe(3000);
    expect($wallet->held_balance_minor)->toBe(5000);

    expect($order->metadata['provider_transaction_id'])->toBe('safety-scen-1');
    expect($order->metadata['provider_purchase_attempted'])->toBeTrue();
    expect(countProviderPurchases($plan->provider_id))->toBe(1);
    expect(WalletLedgerEntry::query()->count())->toBe(0);
});

// Scenario 2: Provider purchase remains ambiguous beyond five minutes.
it('Scenario 2: retains hold and enters manual review when purchase remains ambiguous at 5-minute deadline', function (): void {
    Event::fake();
    config(['esim.reconcile_ttl' => 60]);

    $user = customer('80.00');
    $plan = mappedPricedPlan(5000, 'timeout_missing');
    $wallet = $user->wallets()->firstOrFail();

    $order = app(CheckoutEsimOrder::class)($user, $plan, $wallet, 'safety-scen-2');
    $order->forceFill(['updated_at' => now()->subMinutes(6)])->save();

    (new SweepStaleFulfillmentsJob)->handle();

    $refreshed = $order->refresh();
    expect($refreshed->status)->toBe(OrderStatus::Processing);
    expect($refreshed->fulfillment_status)->toBe(FulfillmentStatus::Processing);
    expect($refreshed->metadata['reconciliation_status'])->toBe('manual_review_required');

    $hold = WalletHold::query()->where('reference_id', $order->id)->firstOrFail();
    expect($hold->status)->toBe(WalletHoldStatus::Active);
    expect($wallet->refresh()->available_balance_minor)->toBe(3000);
    expect($wallet->held_balance_minor)->toBe(5000);
    expect(countProviderPurchases($plan->provider_id))->toBe(1);
});

// Scenario 3: A webhook arrives immediately before wallet release / reconciliation.
it('Scenario 3: completes order and captures hold when webhook profile matches before reconcile', function (): void {
    Event::fake();

    $user = customer('80.00');
    $plan = mappedPricedPlan(5000, 'timeout');
    $wallet = $user->wallets()->firstOrFail();

    $order = app(CheckoutEsimOrder::class)($user, $plan, $wallet, 'safety-scen-3');
    $profile = $order->items->first()->esimProfile;

    $reconciled = app(ReconcileOrderFulfillment::class)($order->fresh());

    expect($reconciled->status)->toBe(OrderStatus::Completed);
    expect($reconciled->payment_status)->toBe(OrderPaymentStatus::Paid);

    $hold = WalletHold::query()->where('reference_id', $order->id)->firstOrFail();
    expect($hold->status)->toBe(WalletHoldStatus::Captured);
    expect($wallet->refresh()->available_balance_minor)->toBe(3000);
    expect($wallet->held_balance_minor)->toBe(0);
});

// Scenario 4: A webhook arrives immediately after the reconciliation deadline.
it('Scenario 4: captures active hold cleanly when late success arrives after deadline entered manual review', function (): void {
    Event::fake();
    config(['esim.reconcile_ttl' => 60]);

    $user = customer('80.00');
    $plan = mappedPricedPlan(5000, 'timeout_missing');
    $wallet = $user->wallets()->firstOrFail();

    $order = app(CheckoutEsimOrder::class)($user, $plan, $wallet, 'safety-scen-4');
    $order->forceFill(['updated_at' => now()->subMinutes(6)])->save();

    // Reconcile deadline reached: enters manual review without releasing hold
    app(ReconcileOrderFulfillment::class)($order->fresh(), true);

    $hold = WalletHold::query()->where('reference_id', $order->id)->firstOrFail();
    expect($hold->status)->toBe(WalletHoldStatus::Active);
    expect($order->refresh()->metadata['reconciliation_status'])->toBe('manual_review_required');

    // Late success: fulfillment profile arrives
    $fulfillment = testFulfillmentResult('late-esim-profile-4');
    $order = app(ApplyEsimFulfillment::class)($order->fresh(), $fulfillment);
    $order = app(CompleteOrderFulfillment::class)($user, $order);

    expect($order->status)->toBe(OrderStatus::Completed);
    expect($order->payment_status)->toBe(OrderPaymentStatus::Paid);
    expect($hold->refresh()->status)->toBe(WalletHoldStatus::Captured);
    expect($wallet->refresh()->available_balance_minor)->toBe(3000);
    expect($wallet->held_balance_minor)->toBe(0);
});

// Scenario 5: Duplicate webhook delivery.
it('Scenario 5: ingests duplicate webhook idempotently without duplicating events or state', function (): void {
    Event::fake();

    $provider = Provider::factory()->fake()->create();
    $accept = app(AcceptProviderWebhook::class);
    $payload = [
        'notifyType' => 'DATA_USAGE',
        'notifyId' => 'safety-wh-dup-1',
        'content' => ['esimTranNo' => 'profile-dup-1', 'orderUsage' => 100],
    ];

    $first = $accept($provider, 'DATA_USAGE', $payload);
    $second = $accept($provider, 'DATA_USAGE', $payload);

    expect($first->id)->toBe($second->id);
    expect(WebhookEvent::query()->where('event_id', 'safety-wh-dup-1')->count())->toBe(1);
});

// Scenario 6: Duplicate scheduler execution.
it('Scenario 6: runs duplicate scheduler sweep without duplicating review state or releases', function (): void {
    Event::fake();
    config(['esim.reconcile_ttl' => 60]);

    $user = customer('80.00');
    $plan = mappedPricedPlan(5000, 'timeout_missing');
    $wallet = $user->wallets()->firstOrFail();

    $order = app(CheckoutEsimOrder::class)($user, $plan, $wallet, 'safety-scen-6');
    $order->forceFill(['updated_at' => now()->subMinutes(6)])->save();

    (new SweepStaleFulfillmentsJob)->handle();
    (new SweepStaleFulfillmentsJob)->handle();

    expect($order->refresh()->status)->toBe(OrderStatus::Processing);
    expect($order->metadata['reconciliation_status'])->toBe('manual_review_required');
    expect(WalletHold::query()->where('reference_id', $order->id)->firstOrFail()->status)->toBe(WalletHoldStatus::Active);
    expect($wallet->refresh()->available_balance_minor)->toBe(3000);
    expect($wallet->held_balance_minor)->toBe(5000);
});

// Scenario 7: Concurrent reconciliation jobs.
it('Scenario 7: captures hold exactly once under concurrent reconciliation jobs', function (): void {
    Event::fake();

    $user = customer('80.00');
    $plan = mappedPricedPlan();
    $wallet = $user->wallets()->firstOrFail();

    $order = app(CreateOrder::class)($user, $plan, 'safety-scen-7');
    $reserved = app(ReserveOrderWithWallet::class)($user, $order, $wallet);
    $fulfilling = app(FulfillOrder::class)($reserved);

    $fulfillment = testFulfillmentResult('concurrent-prof-7');
    $applied = app(ApplyEsimFulfillment::class)($fulfilling, $fulfillment);

    // Run first job
    (new CompleteOrderFulfillmentJob($applied->id))->handle(app(CompleteOrderFulfillment::class));

    // Run concurrent second job
    (new CompleteOrderFulfillmentJob($applied->id))->handle(app(CompleteOrderFulfillment::class));

    $refreshed = $applied->refresh();
    expect($refreshed->status)->toBe(OrderStatus::Completed);
    expect($refreshed->payment_status)->toBe(OrderPaymentStatus::Paid);
    expect(Payment::query()->where('order_id', $refreshed->id)->where('status', PaymentStatus::Paid)->count())->toBe(1);
    expect(WalletHold::query()->where('reference_id', $refreshed->id)->firstOrFail()->status)->toBe(WalletHoldStatus::Captured);
    expect($wallet->refresh()->available_balance_minor)->toBe(3000);
    expect($wallet->held_balance_minor)->toBe(0);
});

// Scenario 8: Definitive provider failure followed by duplicate failure event.
it('Scenario 8: releases hold idempotently on definitive failure and ignores duplicate fail calls', function (): void {
    Event::fake();

    $user = customer('80.00');
    $plan = mappedPricedPlan(5000, 'rejected');
    $wallet = $user->wallets()->firstOrFail();

    $order = app(CheckoutEsimOrder::class)($user, $plan, $wallet, 'safety-scen-8');

    expect($order->status)->toBe(OrderStatus::Failed);
    expect($order->payment_status)->toBe(OrderPaymentStatus::Failed);

    $hold = WalletHold::query()->where('reference_id', $order->id)->firstOrFail();
    expect($hold->status)->toBe(WalletHoldStatus::Released);
    expect($wallet->refresh()->available_balance_minor)->toBe(8000);
    expect($wallet->held_balance_minor)->toBe(0);

    // Duplicate fail call on already failed order
    $duplicate = app(FailOrderFulfillment::class)($order->fresh(), 'Duplicate failure call');

    expect($duplicate->status)->toBe(OrderStatus::Failed);
    expect($wallet->refresh()->available_balance_minor)->toBe(8000);
    expect($wallet->held_balance_minor)->toBe(0);
});

// Scenario 9: Late provider success after the order entered manual review.
it('Scenario 9: successfully transitions manual review order to completed when provider fulfillment arrives', function (): void {
    Event::fake();
    config(['esim.reconcile_ttl' => 60]);

    $user = customer('80.00');
    $plan = mappedPricedPlan(5000, 'timeout_missing');
    $wallet = $user->wallets()->firstOrFail();

    $order = app(CheckoutEsimOrder::class)($user, $plan, $wallet, 'safety-scen-9');
    $order->forceFill(['updated_at' => now()->subMinutes(6)])->save();

    app(ReconcileOrderFulfillment::class)($order->fresh(), true);
    expect($order->refresh()->metadata['reconciliation_status'])->toBe('manual_review_required');

    // Admin verifies with provider that order was created and profile is ready
    $fulfillment = testFulfillmentResult('manual-review-prof-9');
    $completed = app(ApplyEsimFulfillment::class)($order->fresh(), $fulfillment);
    $finalOrder = app(CompleteOrderFulfillment::class)($user, $completed);

    expect($finalOrder->status)->toBe(OrderStatus::Completed);
    expect($finalOrder->payment_status)->toBe(OrderPaymentStatus::Paid);
    expect(WalletHold::query()->where('reference_id', $order->id)->firstOrFail()->status)->toBe(WalletHoldStatus::Captured);
    expect($wallet->refresh()->available_balance_minor)->toBe(3000);
    expect($wallet->held_balance_minor)->toBe(0);
});

// Scenario 10: Repeated purchase submission with the same transaction ID.
it('Scenario 10: refuses to call provider purchase again when replayed with same transaction ID', function (): void {
    Event::fake();

    $user = customer('80.00');
    $plan = mappedPricedPlan(5000, 'timeout');
    $wallet = $user->wallets()->firstOrFail();

    $checkout = app(CheckoutEsimOrder::class);
    $first = $checkout($user, $plan, $wallet, 'safety-scen-10');
    $replayed = $checkout($user, $plan, $wallet->refresh(), 'safety-scen-10');

    expect($first->id)->toBe($replayed->id);
    expect(countProviderPurchases($plan->provider_id))->toBe(1);
    expect($first->metadata['provider_transaction_id'])->toBe('safety-scen-10');
    expect($first->metadata['provider_purchase_attempted'])->toBeTrue();
});

// Scenario 11: Late success after cancellation records explicit financial discrepancy and retains cancelled status
it('Scenario 11: late provider success after cancellation records explicit financial discrepancy without completing order', function (): void {
    Event::fake();

    $user = customer('80.00');
    $plan = mappedPricedPlan(5000, 'timeout_missing');
    $wallet = $user->wallets()->firstOrFail();

    $order = app(CheckoutEsimOrder::class)($user, $plan, $wallet, 'safety-scen-11');
    expect($order->status)->toBe(OrderStatus::Processing);
    expect($order->metadata['provider_purchase_attempted'])->toBeTrue();

    // Admin cancels the order mistakenly assuming provider never fulfilled
    $cancelled = app(\App\Actions\Orders\CancelOrder::class)($user, $order->fresh());
    expect($cancelled->status)->toBe(OrderStatus::Cancelled);
    expect($cancelled->fulfillment_status)->toBe(FulfillmentStatus::Cancelled);
    expect(WalletHold::query()->where('reference_id', $order->id)->firstOrFail()->status)->toBe(WalletHoldStatus::Released);
    expect($wallet->refresh()->available_balance_minor)->toBe(8000);

    // Later, provider delayed fulfillment is discovered / simulated in provider records
    \Illuminate\Support\Facades\Cache::put('fake-provider-purchase:safety-scen-11', [
        'order_id' => 'fake-order-delayed-11',
        'profile_id' => 'fake-esim-delayed-11',
        'plan_id' => 'fake-tr-10gb',
    ], 3600);

    // Later, a fulfillment check runs
    $retried = app(CompleteOrderFulfillment::class)($user, $cancelled->fresh());

    // Order MUST remain Cancelled, hold MUST remain Released, customer NOT credited profile
    expect($retried->status)->toBe(OrderStatus::Cancelled);
    expect($retried->fulfillment_status)->toBe(FulfillmentStatus::Cancelled);
    expect(WalletHold::query()->where('reference_id', $order->id)->firstOrFail()->status)->toBe(WalletHoldStatus::Released);

    // Discrepancy MUST be explicitly flagged in metadata for admin audit
    $meta = $retried->fresh()->metadata;
    expect($meta['reconciliation_status'])->toBe('financial_discrepancy_detected');
    expect($meta['unreconciled_provider_fulfillment'])->toBeArray();
    expect($meta['unreconciled_provider_fulfillment']['provider_order_no'])->toBe('fake-order-delayed-11');
    expect($meta['unreconciled_provider_fulfillment']['iccid'])->not->toBeEmpty();
});
