<?php

declare(strict_types=1);

use App\Actions\Esim\ExpireEsimProfile;
use App\Actions\Wallet\ExpireWalletHold;
use App\Actions\Wallet\PlaceWalletHold;
use App\Domain\MoneyMinor;
use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Enums\WalletHoldStatus;
use App\Integrations\Esim\Manager\ProviderManager;
use App\Jobs\CleanupProviderApiLogsJob;
use App\Jobs\DispatchProviderHealthChecksJob;
use App\Jobs\ExpireEsimProfilesJob;
use App\Jobs\ExpireWalletHoldsJob;
use App\Jobs\HealthCheckProviderJob;
use App\Jobs\SweepStaleFulfillmentsJob;
use App\Models\Provider;
use App\Models\ProviderApiLog;
use App\Models\ProviderHealthCheck;
use App\Models\WalletHold;
use App\Services\Orders\CheckoutEsimOrder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;

it('registers named operational jobs on the scheduler', function (): void {
    $names = collect(app(Schedule::class)->events())
        ->map(fn ($event) => $event->description)
        ->implode(' ');

    expect($names)
        ->toContain('DispatchProviderHealthChecksJob')
        ->toContain('SyncProviderUsageJob')
        ->toContain('DispatchProviderCatalogSyncJob')
        ->toContain('ExpireWalletHoldsJob')
        ->toContain('SweepStaleFulfillmentsJob')
        ->toContain('ExpireEsimProfilesJob')
        ->toContain('CleanupProviderApiLogsJob')
        ->toContain('CleanupAuditLogsJob');
});

it('expires stale active wallet holds and restores available balance', function (): void {
    $user = customer('80.00');
    $wallet = $user->wallets()->firstOrFail();
    $hold = app(PlaceWalletHold::class)($user, $wallet, MoneyMinor::of(2000, 'LYD'), 'sched-hold-1');
    $hold->forceFill(['created_at' => now()->subHours(2)])->save();

    (new ExpireWalletHoldsJob)->handle(app(ExpireWalletHold::class));

    expect($hold->refresh()->status)->toBe(WalletHoldStatus::Expired);
    expect($wallet->refresh()->available_balance_minor)->toBe(8000);
    expect($wallet->held_balance_minor)->toBe(0);

    (new ExpireWalletHoldsJob)->handle(app(ExpireWalletHold::class));
    expect(WalletHold::query()->where('status', WalletHoldStatus::Expired)->count())->toBe(1);
});

it('expires due eSIM profiles through the domain action', function (): void {
    Event::fake();
    $user = customer('80.00');
    $plan = mappedPricedPlan();
    $order = app(CheckoutEsimOrder::class)($user, $plan, $user->wallets()->firstOrFail(), 'sched-esim-exp');
    $profile = $order->items->first()->esimProfile;
    $profile->forceFill(['expires_at' => now()->subDay()])->save();

    (new ExpireEsimProfilesJob)->handle(app(ExpireEsimProfile::class));

    expect($profile->refresh()->status->value)->toBe('expired');
    expect($profile->activationEvents()->where('event_type', 'expired')->count())->toBe(1);
});

it('reconciles stale processing orders at the deadline without a second purchase', function (): void {
    Event::fake();
    config(['esim.reconcile_ttl' => 60]);

    $user = customer('80.00');
    $plan = mappedPricedPlan(5000, 'timeout_missing');
    $order = app(CheckoutEsimOrder::class)($user, $plan, $user->wallets()->firstOrFail(), 'sched-stale');
    expect($order->status)->toBe(OrderStatus::Processing);

    $order->forceFill(['updated_at' => now()->subMinutes(5)])->save();

    (new SweepStaleFulfillmentsJob)->handle();

    expect($order->refresh()->status)->toBe(OrderStatus::Processing);
    expect($order->metadata['reconciliation_status'])->toBe('manual_review_required');
    expect($user->wallets()->first()->refresh()->held_balance_minor)->toBe(5000);
});

it('deletes provider API logs older than the retention window', function (): void {
    $provider = Provider::factory()->fake()->create();
    $old = new ProviderApiLog;
    $old->provider_id = $provider->id;
    $old->service_type = 'esim';
    $old->direction = 'outbound';
    $old->endpoint = 'health';
    $old->created_at = now()->subDays(30);
    $old->save();

    $fresh = new ProviderApiLog;
    $fresh->provider_id = $provider->id;
    $fresh->service_type = 'esim';
    $fresh->direction = 'outbound';
    $fresh->endpoint = 'health';
    $fresh->created_at = now();
    $fresh->save();

    (new CleanupProviderApiLogsJob)->handle();

    expect(ProviderApiLog::query()->count())->toBe(1);
    expect(ProviderApiLog::query()->first()?->id)->toBe($fresh->id);
});

it('keeps error logs until the longer error retention window', function (): void {
    config([
        'esim.api_log_retention_days' => 14,
        'esim.api_log_error_retention_days' => 90,
    ]);

    $provider = Provider::factory()->fake()->create();
    $error = new ProviderApiLog;
    $error->provider_id = $provider->id;
    $error->service_type = 'esim';
    $error->direction = 'outbound';
    $error->endpoint = 'purchase';
    $error->error_message = 'provider timeout';
    $error->created_at = now()->subDays(30);
    $error->save();

    (new CleanupProviderApiLogsJob)->handle();

    expect(ProviderApiLog::query()->whereKey($error->id)->exists())->toBeTrue();
});

it('records fake provider health and skips providers without a driver', function (): void {
    $fake = Provider::factory()->fake()->create();
    $unknown = Provider::factory()->create();

    (new HealthCheckProviderJob($fake->id))->handle(app(ProviderManager::class));
    (new HealthCheckProviderJob($unknown->id))->handle(app(ProviderManager::class));

    expect(ProviderHealthCheck::query()->where('provider_id', $fake->id)->count())->toBe(1);
    expect(ProviderHealthCheck::query()->where('provider_id', $unknown->id)->count())->toBe(0);
});

it('dispatches health checks only for active providers', function (): void {
    Provider::factory()->fake()->create();

    (new DispatchProviderHealthChecksJob)->handle();

    expect(ProviderHealthCheck::query()->count())->toBe(1);
});
