<?php

declare(strict_types=1);

use App\Integrations\Esim\DTOs\PurchaseRequest;
use App\Integrations\Esim\DTOs\RenewRequest;
use App\Integrations\Esim\DTOs\TopUpRequest;
use App\Integrations\Esim\Exceptions\ProviderMalformedException;
use App\Integrations\Esim\Exceptions\ProviderRejectedException;
use App\Integrations\Esim\Exceptions\ProviderTimeoutException;
use App\Integrations\Esim\Exceptions\ProviderUnavailableException;
use App\Integrations\Esim\Exceptions\ProviderUnusableCredentialsException;
use App\Integrations\Esim\Manager\ProviderManager;
use App\Models\Provider;

function fakeDriver(string $scenario = 'success'): App\Integrations\Esim\Contracts\EsimProvider
{
    $provider = Provider::factory()->fake($scenario)->create();

    return app(ProviderManager::class)->driver($provider);
}

it('returns deterministic catalog plans in Travora units', function (): void {
    $plans = fakeDriver()->getPlans();

    expect($plans)->toHaveCount(2);
    expect($plans[0]->externalPlanId)->toBe('fake-tr-10gb');
    expect($plans[0]->dataMb)->toBe(10240);
    expect($plans[0]->costMinor)->toBe(1200);
    expect($plans[0]->currency)->toBe('USD');
});

it('purchases an eSIM into a domain fulfillment DTO', function (): void {
    $result = fakeDriver()->purchase(new PurchaseRequest('fake-tr-10gb', 'idem-success'));

    expect($result->isCompleted())->toBeTrue();
    expect($result->fulfillment?->externalProfileId)->toStartWith('fake-esim-');
    expect($result->fulfillment?->dataTotalMb)->toBe(10240);
    expect($result->fulfillment?->smdpAddress)->toBe('smdp.fake.local');
});

it('rejects a purchase when the fake scenario is rejected', function (): void {
    fakeDriver('rejected')->purchase(new PurchaseRequest('fake-tr-10gb', 'idem-rej'));
})->throws(ProviderRejectedException::class);

it('times out and still allows reconciliation by idempotency key', function (): void {
    $driver = fakeDriver('timeout');
    $key = 'idem-timeout';

    expect(fn () => $driver->purchase(new PurchaseRequest('fake-tr-10gb', $key)))
        ->toThrow(ProviderTimeoutException::class);

    $found = $driver->findPurchase($key);
    expect($found)->not->toBeNull();
    expect($found?->isCompleted())->toBeTrue();
});

it('times out without a remembered purchase when the result never landed', function (): void {
    $driver = fakeDriver('timeout_missing');
    $key = 'idem-timeout-missing';

    expect(fn () => $driver->purchase(new PurchaseRequest('fake-tr-10gb', $key)))
        ->toThrow(ProviderTimeoutException::class);

    expect($driver->findPurchase($key))->toBeNull();
});

it('signals unusable credentials without inventing an auth header', function (): void {
    fakeDriver('unusable_credentials')->health();
})->throws(ProviderUnusableCredentialsException::class);

it('signals temporary unavailability', function (): void {
    fakeDriver('unavailable')->purchase(new PurchaseRequest('fake-tr-10gb', 'idem-unavail'));
})->throws(ProviderUnavailableException::class);

it('rejects a malformed provider result', function (): void {
    fakeDriver('malformed')->purchase(new PurchaseRequest('fake-tr-10gb', 'idem-bad'));
})->throws(ProviderMalformedException::class);

it('returns usage, top-up, and renewal in domain units', function (): void {
    $driver = fakeDriver();
    $usage = $driver->getUsage('fake-esim-abc');
    $topup = $driver->topUp(new TopUpRequest('fake-esim-abc', 'fake-eu-5gb', 'top-1'));
    $renew = $driver->renew(new RenewRequest('fake-esim-abc', 'fake-tr-10gb', 'ren-1'));

    expect($usage->usedMb)->toBe(128);
    expect($usage->remainingMb)->toBe(896);
    expect($topup->status)->toBe('completed');
    expect($topup->dataMbAdded)->toBe(5120);
    expect($renew->validityDaysAdded)->toBe(30);
});

it('only interprets an explicit Travora test fixture as an inbound event', function (): void {
    $driver = fakeDriver();

    expect($driver->interpretInboundEvent(['type' => 'anything.vendor.shaped']))->toBeNull();

    $event = $driver->interpretInboundEvent([
        'travora_fixture' => 'usage',
        'external_profile_id' => 'fake-esim-1',
        'used_mb' => 200,
        'remaining_mb' => 800,
    ]);

    expect($event?->type)->toBe('usage');
    expect($event?->usage?->usedMb)->toBe(200);
});
