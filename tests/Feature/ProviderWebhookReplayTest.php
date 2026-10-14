<?php

declare(strict_types=1);

use App\Models\EsimUsage;
use App\Models\WebhookEvent;
use App\Services\Orders\CheckoutEsimOrder;
use Illuminate\Support\Facades\Event;

it('replays the same provider webhook without duplicating usage records', function (): void {
    Event::fake();

    $user = customer('80.00');
    $plan = mappedPricedPlan();
    $order = app(CheckoutEsimOrder::class)(
        $user,
        $plan,
        $user->wallets()->firstOrFail(),
        'webhook-replay-checkout',
    );
    $profile = $order->items->first()->esimProfile;
    $provider = $plan->provider;

    $payload = [
        'travora_fixture' => 'usage',
        'event_id' => 'evt_replay_usage_1',
        'external_profile_id' => $profile->external_profile_id,
        'used_mb' => 200,
        'remaining_mb' => 800,
    ];

    $this->postJson('/api/v1/webhooks/providers/'.$provider->code, $payload)
        ->assertAccepted()
        ->assertJsonPath('success', true);

    $this->postJson('/api/v1/webhooks/providers/'.$provider->code, $payload)
        ->assertAccepted()
        ->assertJsonPath('success', true);

    expect(WebhookEvent::query()->where('provider_id', $provider->id)->where('event_id', 'evt_replay_usage_1')->count())->toBe(1);
    expect(EsimUsage::query()->where('esim_profile_id', $profile->id)->count())->toBe(1);
    expect($profile->refresh()->data_remaining_mb)->toBe(800);
});
