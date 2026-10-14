<?php

declare(strict_types=1);

use App\Enums\RecordStatus;
use App\Enums\RoleName;
use App\Enums\WalletStatus;
use App\Models\EsimPlan;
use App\Models\EsimPlanPrice;
use App\Models\Provider;
use App\Models\ProviderExternalReference;
use App\Models\ProviderProduct;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', 'Unit');

function staff(\App\Enums\RoleName $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role->value);

    return $user;
}

/**
 * @param  list<string>  $abilities
 */
function actingAsStaff(\App\Enums\RoleName $role, array $abilities = ['*']): User
{
    return \Laravel\Sanctum\Sanctum::actingAs(staff($role), $abilities);
}

function customer(string $balance = '100.000000'): User
{
    $user = User::factory()->create();
    $user->assignRole(RoleName::User->value);

    Wallet::factory()->create([
        'user_id' => $user->id,
        'currency' => 'LYD',
        'available_balance_minor' => (int) round(((float) $balance) * 100),
        'status' => WalletStatus::Active,
    ]);

    return $user;
}

function adminUser(): User
{
    $user = User::factory()->create();
    $user->assignRole(RoleName::SuperAdmin->value);

    return $user;
}

function pricedPlan(int $priceMinor = 5000, string $currency = 'LYD'): EsimPlan
{
    $plan = EsimPlan::factory()->create();

    EsimPlanPrice::query()->create([
        'esim_plan_id' => $plan->id,
        'currency' => $currency,
        'price_minor' => $priceMinor,
        'is_active' => true,
    ]);

    return $plan->load('provider');
}

function mappedPricedPlan(int $priceMinor = 5000, string $scenario = 'success', string $externalPlanId = 'fake-tr-10gb'): EsimPlan
{
    $provider = Provider::factory()->fake($scenario)->create();

    return mappedPlanOnProvider($provider, $priceMinor, $externalPlanId);
}

function mappedPlanOnProvider(Provider $provider, int $priceMinor = 2500, string $externalPlanId = 'fake-eu-5gb'): EsimPlan
{
    $plan = EsimPlan::factory()->create([
        'provider_id' => $provider->id,
        'name' => $externalPlanId === 'fake-eu-5gb' ? 'Europe 5GB 15 days' : 'Turkey 10GB 30 days',
        'data_mb' => $externalPlanId === 'fake-eu-5gb' ? 5120 : 10240,
        'validity_days' => $externalPlanId === 'fake-eu-5gb' ? 15 : 30,
        'status' => RecordStatus::Active,
    ]);

    EsimPlanPrice::query()->create([
        'esim_plan_id' => $plan->id,
        'currency' => 'LYD',
        'price_minor' => $priceMinor,
        'is_active' => true,
    ]);

    $product = new ProviderProduct;
    $product->provider_id = $provider->id;
    $product->product_type = 'esim';
    $product->external_product_id = $externalPlanId;
    $product->name = $plan->name;
    $product->status = RecordStatus::Active;
    $product->raw_data = ['cost_minor' => 950, 'currency' => 'USD'];
    $product->save();

    $plan->provider_product_id = $product->id;
    $plan->save();

    $reference = new ProviderExternalReference;
    $reference->provider_id = $provider->id;
    $reference->entity_type = 'esim_plan';
    $reference->entity_id = $plan->id;
    $reference->external_id = $externalPlanId;
    $reference->external_type = 'plan';
    $reference->save();

    return $plan->load('provider');
}
