<?php

declare(strict_types=1);

use App\Actions\Esim\ActivateEsimProfile;
use App\Actions\Esim\MarkEsimInstalled;
use App\Actions\Esim\RecordEsimUsage;
use App\Actions\Esim\RequestEsimRenewal;
use App\Actions\Esim\RequestEsimTopup;
use App\Actions\Orders\ApplyEsimFulfillment;
use App\Actions\Orders\CreateOrder;
use App\Actions\Orders\FulfillOrder;
use App\Actions\Orders\PayOrderWithWallet;
use App\Domain\EsimFulfillmentResult;
use App\Domain\EsimUsageSnapshot;
use App\Enums\EsimOperationStatus;
use App\Enums\EsimProfileStatus;
use App\Exceptions\ForbiddenOperationException;
use App\Models\EsimProfile;
use Illuminate\Support\Facades\Event;

it('records usage against a ready profile', function (): void {
    $profile = readyProfile();

    $usage = app(RecordEsimUsage::class)($profile, new EsimUsageSnapshot(usedMb: 128, remainingMb: 896));

    expect($usage->used_mb)->toBe(128);
    expect($profile->refresh()->data_remaining_mb)->toBe(896);
    expect($profile->last_synced_at)->not->toBeNull();
    expect($profile->dailyUsage()->count())->toBe(1);
    expect($profile->dailyUsage->first()?->used_mb)->toBe(128);
});

it('requests a top-up idempotently for the owner', function (): void {
    $user = customer('80.00');
    $profile = readyProfile($user);
    $plan = pricedPlan(2500);

    $request = app(RequestEsimTopup::class);
    $first = $request($user, $profile, $plan, 'topup-1');
    $second = $request($user, $profile, $plan, 'topup-1');

    expect($first->id)->toBe($second->id);
    expect($first->status)->toBe(EsimOperationStatus::Pending);
    expect($first->amount_minor)->toBe(2500);
});

it('rejects a top-up from a stranger', function (): void {
    $owner = customer('80.00');
    $stranger = customer('80.00');
    $profile = readyProfile($owner);

    app(RequestEsimTopup::class)($stranger, $profile, pricedPlan(2500), 'topup-stolen');
})->throws(ForbiddenOperationException::class);

it('requests a renewal against a new active plan', function (): void {
    $user = customer('80.00');
    $profile = readyProfile($user);
    $newPlan = pricedPlan(7000);

    $renewal = app(RequestEsimRenewal::class)($user, $profile, $newPlan, 'renew-1');

    expect($renewal->status)->toBe(EsimOperationStatus::Pending);
    expect($renewal->new_plan_id)->toBe($newPlan->id);
    expect($renewal->amount_minor)->toBe(7000);
});

it('marks a ready profile installed then activated', function (): void {
    $user = customer('80.00');
    $profile = readyProfile($user);

    $installed = app(MarkEsimInstalled::class)($user, $profile);
    expect($installed->status)->toBe(EsimProfileStatus::Installed);
    expect($installed->installed_at)->not->toBeNull();
    expect($installed->installations)->toHaveCount(1);

    $again = app(MarkEsimInstalled::class)($user, $installed);
    expect($again->status)->toBe(EsimProfileStatus::Installed);
    expect($again->installations()->count())->toBe(1);

    $activated = app(ActivateEsimProfile::class)($user, $installed);
    expect($activated->status)->toBe(EsimProfileStatus::Activated);
    expect($activated->activated_at)->not->toBeNull();
    expect($activated->orderItem?->order?->status->value)->toBe('completed');
});

function readyProfile(?App\Models\User $user = null): EsimProfile
{
    Event::fake();

    $user ??= customer('80.00');
    $plan = pricedPlan(5000);
    $order = app(CreateOrder::class)($user, $plan, 'esim-'.fake()->uuid());
    $paid = app(PayOrderWithWallet::class)($user, $order, $user->wallets()->firstOrFail());
    $fulfilling = app(FulfillOrder::class)($paid);
    $completed = app(ApplyEsimFulfillment::class)($fulfilling, new EsimFulfillmentResult(
        externalProfileId: 'ext-'.fake()->unique()->numerify('########'),
        iccid: '89'.fake()->unique()->numerify('##################'),
        dataTotalMb: 1024,
        dataRemainingMb: 1024,
    ));

    return $completed->items->first()->esimProfile;
}
