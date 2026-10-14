<?php

declare(strict_types=1);

use App\Actions\Catalog\CreateEsimPlan;
use App\Actions\Catalog\DeactivateEsimPlanPrice;
use App\Actions\Catalog\SetEsimPlanPrice;
use App\Actions\Catalog\SyncPlanCountries;
use App\Actions\Catalog\SyncPlanNetworks;
use App\Actions\Catalog\UpdateEsimPlan;
use App\Actions\Providers\SyncProviderProducts;
use App\Domain\MoneyMinor;
use App\Enums\RecordStatus;
use App\Exceptions\CatalogUnavailableException;
use App\Models\Country;
use App\Models\EsimPlan;
use App\Models\NetworkOperator;
use App\Models\Provider;
use App\Models\ProviderExternalReference;
use App\Models\ProviderProduct;
use App\Queries\Catalog\SearchPublicEsimPlans;
use App\Queries\Catalog\ShowPublicEsimPlan;

it('imports fake provider plans into provider products and canonical Travora plans', function (): void {
    $turkey = Country::factory()->create(['iso2' => 'TR', 'iso3' => 'TUR', 'name' => 'Turkey']);
    Country::factory()->create(['iso2' => 'DE', 'iso3' => 'DEU', 'name' => 'Germany']);
    Country::factory()->create(['iso2' => 'FR', 'iso3' => 'FRA', 'name' => 'France']);
    Country::factory()->create(['iso2' => 'IT', 'iso3' => 'ITA', 'name' => 'Italy']);
    Country::factory()->create(['iso2' => 'ES', 'iso3' => 'ESP', 'name' => 'Spain']);

    $provider = Provider::factory()->fake()->create();
    $result = app(SyncProviderProducts::class)($provider);

    expect($result['imported'])->toBe(2);
    expect(ProviderProduct::query()->where('provider_id', $provider->id)->count())->toBe(2);
    expect(EsimPlan::query()->where('provider_id', $provider->id)->count())->toBe(2);
    expect(ProviderExternalReference::query()->where('provider_id', $provider->id)->where('entity_type', 'esim_plan')->count())->toBe(2);

    $plan = EsimPlan::query()->where('name', 'Turkey 10GB 30 days')->firstOrFail();
    expect($plan->provider_product_id)->not->toBeNull();
    expect($plan->countries->pluck('iso2')->all())->toContain($turkey->iso2);
    expect($plan->prices)->toHaveCount(0);
    expect(app(SearchPublicEsimPlans::class)(['currency' => 'LYD']))->toHaveCount(0);
    expect(app(SearchPublicEsimPlans::class)(['currency' => 'USD']))->toHaveCount(0);

    $replay = app(SyncProviderProducts::class)($provider);
    expect($replay['imported'])->toBe(0);
    expect($replay['updated'])->toBe(2);
    expect(EsimPlan::query()->where('provider_id', $provider->id)->count())->toBe(2);
});

it('deactivates canonical plans when the provider product disappears', function (): void {
    Country::factory()->create(['iso2' => 'TR', 'iso3' => 'TUR']);
    $provider = Provider::factory()->fake()->create();
    app(SyncProviderProducts::class)($provider);

    $extra = new ProviderProduct;
    $extra->provider_id = $provider->id;
    $extra->product_type = 'esim';
    $extra->external_product_id = 'gone-sku';
    $extra->name = 'Gone';
    $extra->status = RecordStatus::Active;
    $extra->save();

    $orphan = EsimPlan::factory()->create([
        'provider_id' => $provider->id,
        'provider_product_id' => $extra->id,
        'status' => RecordStatus::Active,
    ]);

    $result = app(SyncProviderProducts::class)($provider);

    expect($result['disabled'])->toBe(1);
    expect($extra->refresh()->status)->toBe(RecordStatus::Inactive);
    expect($orphan->refresh()->status)->toBe(RecordStatus::Inactive);
});

it('keeps Travora selling price independent from provider cost', function (): void {
    $provider = Provider::factory()->create();
    $plan = app(CreateEsimPlan::class)($provider, [
        'name' => 'Libya 3GB',
        'data_mb' => 3072,
        'validity_days' => 15,
    ]);

    $price = app(SetEsimPlanPrice::class)($plan, MoneyMinor::of(4500, 'LYD'));

    expect($price->price_minor)->toBe(4500);
    expect($price->currency)->toBe('LYD');
    expect($price->is_active)->toBeTrue();
    expect($plan->provider_product_id)->toBeNull();
});

it('replaces the active price window for a currency', function (): void {
    $plan = EsimPlan::factory()->create();
    $first = app(SetEsimPlanPrice::class)($plan, MoneyMinor::of(1000, 'LYD'));
    $second = app(SetEsimPlanPrice::class)($plan, MoneyMinor::of(1200, 'LYD'));

    expect($first->refresh()->is_active)->toBeFalse();
    expect($second->is_active)->toBeTrue();
    expect($second->price_minor)->toBe(1200);
});

it('rejects an inverted price window', function (): void {
    $plan = EsimPlan::factory()->create();

    app(SetEsimPlanPrice::class)(
        $plan,
        MoneyMinor::of(1000, 'LYD'),
        now()->addDay(),
        now(),
    );
})->throws(CatalogUnavailableException::class);

it('hides unpublished or unpriced plans from the public catalog', function (): void {
    $country = Country::factory()->create(['iso2' => 'LY', 'iso3' => 'LBY', 'name' => 'Libya']);
    $visible = EsimPlan::factory()->create(['name' => 'Visible Plan', 'status' => RecordStatus::Active]);
    app(SyncPlanCountries::class)($visible, [$country->id]);
    app(SetEsimPlanPrice::class)($visible, MoneyMinor::of(2000, 'LYD'));

    $hiddenInactive = EsimPlan::factory()->create(['status' => RecordStatus::Inactive]);
    app(SetEsimPlanPrice::class)($hiddenInactive, MoneyMinor::of(2000, 'LYD'));

    EsimPlan::factory()->create(['status' => RecordStatus::Active, 'name' => 'No Price']);

    $results = app(SearchPublicEsimPlans::class)(['currency' => 'LYD']);

    expect($results)->toHaveCount(1);
    expect($results->first()->id)->toBe($visible->id);
    expect($results->first()->priceMinor)->toBe(2000);
    expect($results->first())->not->toHaveProperty('provider_id');
    expect($results->first())->not->toHaveProperty('provider_product_id');
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

    $results = app(SearchPublicEsimPlans::class)(['currency' => 'LYD', 'country_iso2' => 'LY']);
    $shown = app(ShowPublicEsimPlan::class)($libyaPlan, 'LYD');

    expect($results)->toHaveCount(1);
    expect($results->first()->name)->toBe('Libya Plan');
    expect($shown->countries[0]['iso2'])->toBe('LY');
    expect($shown)->not->toHaveProperty('provider_id');
    expect($shown)->not->toHaveProperty('provider_product_id');
});

it('attaches networks and can unpublish a plan', function (): void {
    $plan = EsimPlan::factory()->create();
    $network = NetworkOperator::factory()->create(['name' => 'Almadar']);
    app(SyncPlanNetworks::class)($plan, [$network->id]);
    app(SetEsimPlanPrice::class)($plan, MoneyMinor::of(1500, 'LYD'));

    expect($plan->fresh()->networks)->toHaveCount(1);

    app(UpdateEsimPlan::class)($plan, ['status' => RecordStatus::Inactive]);

    expect(app(SearchPublicEsimPlans::class)(['currency' => 'LYD']))->toHaveCount(0);
});

it('deactivates a price so quote fails', function (): void {
    $plan = EsimPlan::factory()->create();
    $price = app(SetEsimPlanPrice::class)($plan, MoneyMinor::of(1500, 'LYD'));
    app(DeactivateEsimPlanPrice::class)($price);

    app(ShowPublicEsimPlan::class)($plan, 'LYD');
})->throws(CatalogUnavailableException::class);
