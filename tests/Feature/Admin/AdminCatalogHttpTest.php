<?php

declare(strict_types=1);

use App\Actions\Catalog\SetEsimPlanPrice;
use App\Domain\MoneyMinor;
use App\Enums\EsimProfileStatus;
use App\Enums\RoleName;
use App\Models\Country;
use App\Models\EsimPlan;
use App\Models\EsimProfile;
use App\Models\Provider;
use Laravel\Sanctum\Sanctum;

it('creates a canonical plan price through existing catalog actions', function (): void {
    $provider = Provider::factory()->create();
    Sanctum::actingAs(staff(RoleName::Operator), ['*']);

    $create = $this->postJson('/api/v1/admin/esim/plans', [
        'provider_id' => $provider->id,
        'name' => 'Libya 3GB',
        'data_mb' => 3072,
        'validity_days' => 15,
    ]);

    $create->assertCreated()
        ->assertJsonPath('data.name', 'Libya 3GB')
        ->assertJsonPath('data.provider_id', $provider->id)
        ->assertJsonPath('data.provider_name', $provider->name)
        ->assertJsonPath('data.provider_product_id', null)
        ->assertJsonPath('data.provider_product', null);

    $planId = $create->json('data.id');

    $this->postJson('/api/v1/admin/esim/plans/'.$planId.'/prices', [
        'price_minor' => 4500,
        'currency' => 'LYD',
    ])->assertCreated()
        ->assertJsonPath('data.prices.0.price_minor', 4500)
        ->assertJsonPath('data.prices.0.currency', 'LYD');
});

it('shows the stored vendor wholesale price beside the app selling price', function (): void {
    $plan = mappedPricedPlan(4500);
    Sanctum::actingAs(staff(RoleName::Operator), ['*']);

    $this->getJson('/api/v1/admin/esim/plans/'.$plan->id)
        ->assertOk()
        ->assertJsonPath('data.provider_product.cost_minor', 950)
        ->assertJsonPath('data.provider_product.currency', 'USD')
        ->assertJsonPath('data.prices.0.price_minor', 4500)
        ->assertJsonPath('data.prices.0.currency', 'LYD');

    $this->getJson('/api/v1/esim-plans/'.$plan->id)
        ->assertOk()
        ->assertJsonPath('data.price_minor', 4500)
        ->assertJsonPath('data.currency', 'LYD')
        ->assertJsonMissingPath('data.provider_product');
});

it('lists esim profiles for support without usage sync', function (): void {
    Sanctum::actingAs(staff(RoleName::Support), ['*']);

    $this->getJson('/api/v1/admin/esim/profiles')->assertOk();

    $plan = EsimPlan::factory()->create();
    $profile = new EsimProfile;
    $profile->user_id = customer()->id;
    $profile->plan_id = $plan->id;
    $profile->provider_id = $plan->provider_id;
    $profile->status = EsimProfileStatus::Ready;
    $profile->save();

    $this->postJson('/api/v1/admin/esim/profiles/'.$profile->id.'/usage/sync')
        ->assertForbidden();
});

it('filters esim profiles by iccid search', function (): void {
    Sanctum::actingAs(staff(RoleName::Support), ['*']);
    $plan = EsimPlan::factory()->create();
    $profile = new EsimProfile;
    $profile->user_id = customer()->id;
    $profile->plan_id = $plan->id;
    $profile->provider_id = $plan->provider_id;
    $profile->status = EsimProfileStatus::Ready;
    $profile->iccid = '8999900000000000001';
    $profile->save();

    $this->getJson('/api/v1/admin/esim/profiles?search=8999900000000000001')
        ->assertOk()
        ->assertJsonPath('data.0.id', $profile->id);

    $this->getJson('/api/v1/admin/esim/profiles?search=does-not-match')
        ->assertOk()
        ->assertJsonPath('data', []);
});

it('syncs plan countries through the existing action', function (): void {
    $country = Country::factory()->create(['iso2' => 'LY', 'iso3' => 'LBY']);
    $plan = EsimPlan::factory()->create();
    app(SetEsimPlanPrice::class)($plan, MoneyMinor::of(1000, 'LYD'));
    Sanctum::actingAs(staff(RoleName::Operator), ['*']);

    $this->putJson('/api/v1/admin/esim/plans/'.$plan->id.'/countries', [
        'country_ids' => [$country->id],
    ])->assertOk()
        ->assertJsonPath('data.countries.0.iso2', 'LY');
});
