<?php

declare(strict_types=1);

use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Models\EsimProfile;
use App\Models\Order;
use App\Models\Wallet;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;

it('lists public plans without provider leakage then checks out through the existing commerce flow', function (): void {
    Event::fake();
    $user = customer('80.00');
    $plan = mappedPricedPlan();
    Sanctum::actingAs($user);

    $catalog = $this->getJson('/api/v1/esim-plans');
    $catalog->assertOk();
    expect($catalog->json('data.0'))->not->toHaveKey('provider_id');

    $created = $this->postJson('/api/v1/orders', [
        'plan_id' => $plan->id,
        'quantity' => 1,
    ], [
        'Idempotency-Key' => 'mobile-checkout-1',
    ]);

    $created->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.status', OrderStatus::Completed->value)
        ->assertJsonPath('data.fulfillment_status', FulfillmentStatus::Completed->value)
        ->assertJsonPath('data.currency', 'LYD')
        ->assertJsonMissingPath('data.provider_id');

    $replay = $this->postJson('/api/v1/orders', [
        'plan_id' => $plan->id,
        'quantity' => 1,
    ], [
        'Idempotency-Key' => 'mobile-checkout-1',
    ]);

    $replay->assertCreated();
    expect($replay->json('data.id'))->toBe($created->json('data.id'));
    expect(Order::query()->where('user_id', $user->id)->count())->toBe(1);
    expect(EsimProfile::query()->where('user_id', $user->id)->count())->toBe(1);
    expect($user->wallets()->first()?->refresh()->available_balance_minor)->toBe(3000);
});

it('rejects a purchase when the wallet has insufficient funds', function (): void {
    Event::fake();
    $user = customer('1.00');
    $plan = mappedPricedPlan();
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/orders', [
        'plan_id' => $plan->id,
    ], [
        'Idempotency-Key' => 'mobile-insufficient',
    ])->assertUnprocessable()
        ->assertJsonPath('success', false)
        ->assertJsonPath('code', 'INSUFFICIENT_BALANCE');
});

it('lists owned esims and returns a Travora installation payload', function (): void {
    Event::fake();
    $user = customer('80.00');
    $plan = mappedPricedPlan();
    Sanctum::actingAs($user);

    $order = $this->postJson('/api/v1/orders', ['plan_id' => $plan->id], [
        'Idempotency-Key' => 'mobile-esim-1',
    ])->assertCreated();

    $profileId = $order->json('data.items.0.esim_profile_id');

    $this->getJson('/api/v1/esims')
        ->assertOk()
        ->assertJsonPath('data.0.id', $profileId)
        ->assertJsonMissingPath('data.0.provider_id');

    $installation = $this->getJson('/api/v1/esims/'.$profileId.'/installation');
    $installation->assertOk()
        ->assertJsonPath('data.profile_id', $profileId)
        ->assertJsonMissingPath('data.activation_code_encrypted');
    expect($installation->json('data.qr_payload'))->toStartWith('LPA:');
});

it('credits a customer wallet in minor units', function (): void {
    $user = customer('10.00');
    $user->givePermissionTo('wallets.adjust');
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/wallet/topup', [
        'amount_minor' => 4000,
        'currency' => 'LYD',
    ], [
        'Idempotency-Key' => 'purchase-wallet-topup-1',
    ])->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.available_balance_minor', 5000)
        ->assertJsonPath('data.currency', 'LYD');

    expect(Wallet::query()->where('user_id', $user->id)->value('available_balance_minor'))->toBe(5000);
});
