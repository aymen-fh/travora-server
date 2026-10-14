<?php

declare(strict_types=1);

use App\Enums\ProviderCapability;
use App\Exceptions\CapabilityNotSupportedException;
use App\Integrations\Esim\Drivers\Fake\FakeProvider;
use App\Integrations\Esim\Drivers\Template\TemplateEsimProvider;
use App\Integrations\Esim\DTOs\PurchaseRequest;
use App\Integrations\Esim\Exceptions\ProviderNotImplementedException;
use App\Integrations\Esim\Manager\ProviderManager;
use App\Models\Provider;
use App\Models\ProviderService;

it('resolves FakeProvider by provider code without switching in domain code', function (): void {
    $provider = Provider::factory()->fake()->create();

    $driver = app(ProviderManager::class)->driver($provider);

    expect($driver)->toBeInstanceOf(FakeProvider::class);
    expect($driver->code())->toBe('fake');
    expect($driver->capabilities()->supports(ProviderCapability::Purchase))->toBeTrue();
});

it('resolves the template driver as an empty skeleton', function (): void {
    $provider = Provider::factory()->template()->create();

    $driver = app(ProviderManager::class)->driver($provider);

    expect($driver)->toBeInstanceOf(TemplateEsimProvider::class);
    expect(fn () => $driver->purchase(new PurchaseRequest('any', 'idem-1')))
        ->toThrow(ProviderNotImplementedException::class);
});

it('blocks unsupported capabilities before the driver is used', function (): void {
    $provider = Provider::factory()->fake()->create();
    $service = new ProviderService;
    $service->provider_id = $provider->id;
    $service->service_type = 'esim';
    $service->is_enabled = true;
    $service->config = ['capabilities' => ['purchase' => true, 'topup' => false]];
    $service->save();

    $manager = app(ProviderManager::class);
    $driver = $manager->driver($provider);

    $manager->assertCapability($driver, ProviderCapability::Topup);
})->throws(CapabilityNotSupportedException::class);

it('refuses an unregistered provider code', function (): void {
    $provider = Provider::factory()->create(['code' => 'unknown-vendor']);

    app(ProviderManager::class)->driver($provider);
})->throws(InvalidArgumentException::class);
