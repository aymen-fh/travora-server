<?php

declare(strict_types=1);

use App\Actions\Esim\ActivateEsimProfile;
use App\Actions\Esim\BuildEsimInstallationPayload;
use App\Actions\Esim\ExpireEsimProfile;
use App\Actions\Esim\FailEsimInstallation;
use App\Actions\Esim\FailEsimTopup;
use App\Actions\Esim\MarkEsimInstalled;
use App\Actions\Esim\RefreshEsimUsage;
use App\Actions\Esim\RenewEsim;
use App\Actions\Esim\TopUpEsim;
use App\Enums\EsimOperationStatus;
use App\Enums\EsimProfileStatus;
use App\Enums\InstallationStatus;
use App\Enums\OrderStatus;
use App\Jobs\SyncProviderUsageJob;
use App\Models\EsimUsage;
use App\Models\EsimUsageDaily;
use App\Services\Orders\CheckoutEsimOrder;
use Illuminate\Support\Facades\Event;

function readyMappedProfile(): array
{
    Event::fake();

    $user = customer('80.00');
    $plan = mappedPricedPlan();
    $order = app(CheckoutEsimOrder::class)(
        $user,
        $plan,
        $user->wallets()->firstOrFail(),
        'esim-life-'.fake()->uuid(),
    );

    return [$user, $order->items->first()->esimProfile, $plan, $order];
}

it('builds a Travora installation payload without exposing encrypted provider internals', function (): void {
    [$user, $profile] = readyMappedProfile();

    $payload = app(BuildEsimInstallationPayload::class)($user, $profile);
    $array = $payload->toArray();

    expect($profile->status)->toBe(EsimProfileStatus::Ready);
    expect($array)->toHaveKeys(['profile_id', 'iccid', 'smdp_address', 'matching_id', 'activation_code', 'qr_payload', 'confirmation_code_masked']);
    expect($array)->not->toHaveKey('activation_code_encrypted');
    expect($payload->qrPayload)->toStartWith('LPA:');
    expect($profile->toArray())->not->toHaveKey('activation_code_encrypted');
    expect($profile->toArray())->not->toHaveKey('confirmation_code_encrypted');
});

it('keeps installation, activation, and order status independent', function (): void {
    [$user, $profile, $plan, $order] = readyMappedProfile();

    $failedInstall = app(FailEsimInstallation::class)($user, $profile, 'QR scan failed');
    expect($failedInstall->status)->toBe(InstallationStatus::Failed->value);
    expect($profile->refresh()->status)->toBe(EsimProfileStatus::Ready);
    expect($order->refresh()->status)->toBe(OrderStatus::Completed);

    $installed = app(MarkEsimInstalled::class)($user, $profile);
    expect($installed->status)->toBe(EsimProfileStatus::Installed);
    expect($installed->activated_at)->toBeNull();
    expect($order->refresh()->status)->toBe(OrderStatus::Completed);

    $activated = app(ActivateEsimProfile::class)($user, $installed);
    expect($activated->status)->toBe(EsimProfileStatus::Activated);
    expect($activated->installed_at)->not->toBeNull();
    expect($order->refresh()->fulfillment_status->value)->toBe('completed');
});

it('syncs provider usage into historical and daily rows', function (): void {
    [, $profile] = readyMappedProfile();

    $usage = app(RefreshEsimUsage::class)($profile);

    expect($usage->used_mb)->toBe(128);
    expect($usage->remaining_mb)->toBe(896);
    expect($profile->refresh()->data_remaining_mb)->toBe(896);
    expect(EsimUsage::query()->where('esim_profile_id', $profile->id)->count())->toBe(1);
    expect(EsimUsageDaily::query()->where('esim_profile_id', $profile->id)->count())->toBe(1);

    app(RefreshEsimUsage::class)($profile);
    expect(EsimUsage::query()->where('esim_profile_id', $profile->id)->count())->toBe(2);
    expect(EsimUsageDaily::query()->where('esim_profile_id', $profile->id)->count())->toBe(1);
});

it('applies a fake provider top-up idempotently and does not retry after timeout', function (): void {
    [$user, $profile, $plan] = readyMappedProfile();
    $topupPlan = mappedPlanOnProvider($plan->provider, 2500, 'fake-eu-5gb');
    $beforeRemaining = $profile->data_remaining_mb;

    $complete = app(TopUpEsim::class);
    $first = $complete($user, $profile, $topupPlan, 'life-topup-1');
    $second = $complete($user, $profile, $topupPlan, 'life-topup-1');

    expect($first->id)->toBe($second->id);
    expect($first->status)->toBe(EsimOperationStatus::Completed);
    expect($first->amount_minor)->toBe(2500);
    expect($profile->refresh()->data_remaining_mb)->toBe($beforeRemaining + 5120);
    expect($profile->topups()->count())->toBe(1);
});

it('leaves a timed-out top-up in processing instead of purchasing again', function (): void {
    [$user, $profile, $plan] = readyMappedProfile();
    $topupPlan = mappedPlanOnProvider($plan->provider, 2500, 'fake-eu-5gb');
    $plan->provider->update(['metadata' => ['scenario' => 'timeout']]);

    $complete = app(TopUpEsim::class);
    $pending = $complete($user, $profile, $topupPlan, 'life-topup-timeout');
    $replayed = $complete($user, $profile, $topupPlan, 'life-topup-timeout');

    expect($pending->id)->toBe($replayed->id);
    expect($replayed->status)->toBe(EsimOperationStatus::Processing);
    expect($profile->topups()->count())->toBe(1);

    $failed = app(FailEsimTopup::class)($replayed, 'reconcile deadline');
    expect($failed->status)->toBe(EsimOperationStatus::Failed);
});

it('renews a profile through the fake provider without changing the original order', function (): void {
    [$user, $profile, $plan, $order] = readyMappedProfile();
    $newPlan = mappedPlanOnProvider($plan->provider, 7000, 'fake-eu-5gb');
    $expiresBefore = $profile->expires_at;

    $renewal = app(RenewEsim::class)($user, $profile, $newPlan, 'life-renew-1');
    $replayed = app(RenewEsim::class)($user, $profile, $newPlan, 'life-renew-1');

    expect($replayed->id)->toBe($renewal->id);
    expect($renewal->status)->toBe(EsimOperationStatus::Completed);
    expect($profile->refresh()->plan_id)->toBe($newPlan->id);
    expect($profile->expires_at?->greaterThan($expiresBefore))->toBeTrue();
    expect($order->refresh()->status)->toBe(OrderStatus::Completed);
});

it('expires a profile through the state machine', function (): void {
    [, $profile] = readyMappedProfile();

    $expired = app(ExpireEsimProfile::class)($profile);

    expect($expired->status)->toBe(EsimProfileStatus::Expired);
    expect($expired->activationEvents()->where('event_type', 'expired')->count())->toBe(1);
});

it('pulls usage from the fake provider through the scheduled job', function (): void {
    [, $profile] = readyMappedProfile();

    (new SyncProviderUsageJob)->handle(app(RefreshEsimUsage::class));

    expect(EsimUsage::query()->where('esim_profile_id', $profile->id)->count())->toBe(1);
    expect($profile->refresh()->last_synced_at)->not->toBeNull();
});
