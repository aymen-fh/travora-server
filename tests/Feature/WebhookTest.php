<?php

declare(strict_types=1);

use App\Enums\WebhookEventStatus;
use App\Models\Provider;
use App\Models\WebhookEvent;
use App\Services\Orders\CheckoutEsimOrder;
use Illuminate\Support\Facades\Event;

it('accepts provider webhooks without a user token', function (): void {
    Provider::factory()->fake()->create();

    $this->postJson('/api/v1/webhooks/providers/fake', [
        'event_id' => 'evt-guest',
        'hello' => 'world',
    ], [
        'X-Signature' => 'travora-fixture',
    ])->assertAccepted()
        ->assertJsonPath('success', true)
        ->assertJsonStructure(['success', 'message', 'data', 'meta']);
});

it('stores provider webhooks idempotently', function (): void {
    $provider = Provider::factory()->fake()->create();
    $payload = [
        'event_id' => 'evt_1',
        'type' => 'ping',
        'hello' => 'world',
    ];
    $headers = ['X-Signature' => 'travora-fixture'];

    $first = $this->postJson('/api/v1/webhooks/providers/fake', $payload, $headers)->assertAccepted();
    $second = $this->postJson('/api/v1/webhooks/providers/fake', $payload, $headers)->assertAccepted();

    expect($second->json('data.id'))->toBe($first->json('data.id'));
    expect(WebhookEvent::query()->where('provider_id', $provider->id)->count())->toBe(1);
    expect(WebhookEvent::query()->first()->status->value)->toBeIn(['pending', 'processed', 'ignored']);
});

it('processes a valid fake usage fixture', function (): void {
    Event::fake();

    $user = customer('80.00');
    $plan = mappedPricedPlan();
    $order = app(CheckoutEsimOrder::class)(
        $user,
        $plan,
        $user->wallets()->firstOrFail(),
        'wh-http-'.fake()->uuid(),
    );
    $profile = $order->items->first()->esimProfile;
    $provider = $plan->provider;

    $payload = [
        'travora_fixture' => 'usage',
        'event_id' => 'wh-usage-http-1',
        'external_profile_id' => $profile->external_profile_id,
        'used_mb' => 200,
        'remaining_mb' => 800,
        'api_key' => 'super-secret',
    ];

    $this->postJson('/api/v1/webhooks/providers/'.$provider->code, $payload)
        ->assertAccepted()
        ->assertJsonPath('success', true);

    $event = WebhookEvent::query()->where('provider_id', $provider->id)->firstOrFail();

    expect($event->status)->toBeIn([WebhookEventStatus::Pending, WebhookEventStatus::Processed]);
    expect($event->status->value)->not->toBe('received');
});

it('ignores unknown provider payloads', function (): void {
    $provider = Provider::factory()->fake()->create();

    $this->postJson('/api/v1/webhooks/providers/fake', [
        'event_id' => 'evt-unknown',
        'raw' => true,
    ], [
        'X-Signature' => 'travora-fixture',
    ])->assertAccepted();

    $event = WebhookEvent::query()->where('provider_id', $provider->id)->firstOrFail();

    expect($event->status)->toBe(WebhookEventStatus::Ignored);
});

it('ignores invalid webhook signatures', function (): void {
    $provider = Provider::factory()->fake()->create();

    $this->postJson('/api/v1/webhooks/providers/fake', [
        'event_id' => 'evt-bad-sig',
        'type' => 'esim.usage',
        'used_mb' => 1,
    ], [
        'X-Signature' => 'not-a-real-vendor-hmac',
    ])->assertAccepted();

    $event = WebhookEvent::query()->where('provider_id', $provider->id)->firstOrFail();

    expect($event->status)->toBe(WebhookEventStatus::Ignored);
    expect($event->signature_valid)->toBeFalse();
});
