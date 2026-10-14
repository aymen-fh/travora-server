<?php

declare(strict_types=1);

use App\Actions\Catalog\SetEsimPlanPrice;
use App\Actions\Catalog\SyncPlanCountries;
use App\Domain\MoneyMinor;
use App\Enums\RecordStatus;
use App\Models\Country;
use App\Models\EsimPlan;

it('lists public countries with ERD v2 fields', function (): void {
    $qatar = Country::factory()->create([
        'iso2' => 'QA',
        'iso3' => 'QAT',
        'name' => 'Qatar',
        'name_ar' => 'قطر',
        'flag_url' => 'https://example.test/qa.svg',
        'is_active' => true,
    ]);
    Country::factory()->create([
        'iso2' => 'ZZ',
        'iso3' => 'ZZZ',
        'name' => 'Hiddenland',
        'is_active' => false,
    ]);

    $response = $this->getJson('/api/v1/countries');

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                ['id', 'name', 'name_ar', 'iso2', 'flag_url'],
            ],
            'meta',
        ]);

    $ids = collect($response->json('data'))->pluck('id');
    $qatarPayload = collect($response->json('data'))->firstWhere('iso2', 'QA');

    expect($ids)->toContain($qatar->id);
    expect($ids)->not->toContain(Country::query()->where('iso2', 'ZZ')->value('id'));
    expect($qatarPayload['name'])->toBe('Qatar');
    expect($qatarPayload['name_ar'])->toBe('قطر');
    expect($qatarPayload['flag_url'])->toBe('https://example.test/qa.svg');
    expect($qatarPayload)->not->toHaveKey('provider_id');
});

it('lists only active priced public eSIM plans without provider leakage', function (): void {
    $country = Country::factory()->create(['iso2' => 'LY', 'iso3' => 'LBY', 'name' => 'Libya']);
    $visible = EsimPlan::factory()->create(['name' => 'Visible Plan', 'status' => RecordStatus::Active]);
    app(SyncPlanCountries::class)($visible, [$country->id]);
    app(SetEsimPlanPrice::class)($visible, MoneyMinor::of(2000, 'LYD'));

    $hiddenInactive = EsimPlan::factory()->create(['name' => 'Inactive Plan', 'status' => RecordStatus::Inactive]);
    app(SetEsimPlanPrice::class)($hiddenInactive, MoneyMinor::of(2000, 'LYD'));

    EsimPlan::factory()->create(['status' => RecordStatus::Active, 'name' => 'No Price']);

    $response = $this->getJson('/api/v1/esim-plans');

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.0.id', $visible->id)
        ->assertJsonPath('data.0.name', 'Visible Plan')
        ->assertJsonPath('data.0.currency', 'LYD')
        ->assertJsonPath('data.0.price_minor', 2000)
        ->assertJsonMissingPath('data.0.provider_id')
        ->assertJsonMissingPath('data.0.provider_product_id')
        ->assertJsonMissingPath('data.0.external_product_id');

    $ids = collect($response->json('data'))->pluck('id');
    expect($ids)->toHaveCount(1);
    expect($ids)->not->toContain($hiddenInactive->id);
    expect(json_encode($response->json()))->not->toContain($visible->provider_id);
});

it('shows a public eSIM plan through the canonical catalog query', function (): void {
    $libya = Country::factory()->create(['iso2' => 'LY', 'iso3' => 'LBY', 'name' => 'Libya']);
    $plan = EsimPlan::factory()->create(['name' => 'Libya Plan', 'status' => RecordStatus::Active]);
    app(SyncPlanCountries::class)($plan, [$libya->id]);
    app(SetEsimPlanPrice::class)($plan, MoneyMinor::of(3000, 'LYD'));

    $this->getJson('/api/v1/esim-plans/'.$plan->id)
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.id', $plan->id)
        ->assertJsonPath('data.name', 'Libya Plan')
        ->assertJsonPath('data.price_minor', 3000)
        ->assertJsonPath('data.currency', 'LYD')
        ->assertJsonMissingPath('data.provider_id')
        ->assertJsonMissingPath('data.provider_product_id')
        ->assertJsonMissingPath('data.external_product_id');
});

it('hides unpriced and inactive plans from public show', function (): void {
    $unpriced = EsimPlan::factory()->create(['status' => RecordStatus::Active, 'name' => 'No Price']);
    $inactive = EsimPlan::factory()->create(['status' => RecordStatus::Inactive]);
    app(SetEsimPlanPrice::class)($inactive, MoneyMinor::of(2000, 'LYD'));

    $this->getJson('/api/v1/esim-plans/'.$unpriced->id)
        ->assertUnprocessable()
        ->assertJsonPath('success', false)
        ->assertJsonPath('code', 'CATALOG_UNAVAILABLE')
        ->assertJsonStructure(['success', 'message', 'errors', 'code']);

    $this->getJson('/api/v1/esim-plans/'.$inactive->id)
        ->assertUnprocessable()
        ->assertJsonPath('success', false)
        ->assertJsonPath('code', 'CATALOG_UNAVAILABLE');
});

it('filters the public catalog by country without exposing provider internals', function (): void {
    $libya = Country::factory()->create(['iso2' => 'LY', 'iso3' => 'LBY', 'name' => 'Libya']);
    $turkey = Country::factory()->create(['iso2' => 'TR', 'iso3' => 'TUR', 'name' => 'Turkey']);

    $libyaPlan = EsimPlan::factory()->create(['name' => 'Libya Plan']);
    $turkeyPlan = EsimPlan::factory()->create(['name' => 'Turkey Plan']);
    app(SyncPlanCountries::class)($libyaPlan, [$libya->id]);
    app(SyncPlanCountries::class)($turkeyPlan, [$turkey->id]);
    app(SetEsimPlanPrice::class)($libyaPlan, MoneyMinor::of(3000, 'LYD'));
    app(SetEsimPlanPrice::class)($turkeyPlan, MoneyMinor::of(4000, 'LYD'));

    $response = $this->getJson('/api/v1/esim-plans?country_iso2=LY&currency=LYD');

    $response->assertOk()->assertJsonPath('data.0.name', 'Libya Plan');
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0'))->not->toHaveKey('provider_id');
    expect($response->json('data.0'))->not->toHaveKey('provider_product_id');
    expect($response->json('data.0'))->not->toHaveKey('external_product_id');
});
