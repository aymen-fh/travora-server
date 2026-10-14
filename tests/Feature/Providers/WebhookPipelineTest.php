<?php

declare(strict_types=1);

use App\Actions\Providers\ProcessProviderWebhook;
use App\Actions\Providers\RetryWebhookEvent;
use App\Enums\WebhookEventStatus;
use App\Models\EsimUsage;
use App\Models\WebhookEvent;
use App\Services\Orders\CheckoutEsimOrder;
use App\Services\Providers\AcceptProviderWebhook;
use Illuminate\Support\Facades\Event;

function provisionedFakeProfile(): array
{
    Event::fake();

    $user = customer('80.00');
    $plan = mappedPricedPlan();
    $order = app(CheckoutEsimOrder::class)(
        $user,
        $plan,
        $user->wallets()->firstOrFail(),
        'wh-'.fake()->uuid(),
    );

    return [$order->items->first()->esimProfile, $plan->provider];
}

it('applies a fake usage fixture once and ignores duplicate ingest', function (): void {
    [$profile, $provider] = provisionedFakeProfile();
    $accept = app(AcceptProviderWebhook::class);
    $payload = [
        'travora_fixture' => 'usage',
        'external_profile_id' => $profile->external_profile_id,
        'used_mb' => 200,
        'remaining_mb' => 800,
        'api_key' => 'super-secret',
    ];

    $first = $accept($provider, 'esim.usage', $payload, 'wh-usage-1');
    $second = $accept($provider, 'esim.usage', $payload, 'wh-usage-1');

    expect($second->id)->toBe($first->id);
    expect($first->fresh()->status)->toBe(WebhookEventStatus::Processed);
    expect($profile->refresh()->data_remaining_mb)->toBe(800);
    expect(EsimUsage::query()->where('esim_profile_id', $profile->id)->count())->toBe(1);
    expect(WebhookEvent::query()->where('provider_id', $provider->id)->count())->toBe(1);
    expect($first->maskedPayload()['api_key'])->toBe('***');
    expect($first->toArray())->not->toHaveKey('signature');
});

it('does not apply domain changes when the signature is invalid', function (): void {
    [$profile, $provider] = provisionedFakeProfile();

    $event = app(AcceptProviderWebhook::class)(
        $provider,
        'esim.usage',
        [
            'external_profile_id' => $profile->external_profile_id,
            'used_mb' => 999,
            'remaining_mb' => 1,
        ],
        'wh-bad-sig',
        'not-a-real-vendor-hmac',
    );

    expect($event->fresh()->status)->toBe(WebhookEventStatus::Ignored);
    expect($event->signature_valid)->toBeFalse();
    expect($profile->refresh()->data_remaining_mb)->not->toBe(1);
});

it('fails a usage event for an unknown profile and retries until dead letter', function (): void {
    config(['esim.webhook_max_attempts' => 1]);

    $provider = App\Models\Provider::factory()->fake()->create();
    $payload = [
        'travora_fixture' => 'usage',
        'external_profile_id' => 'missing-esim',
        'used_mb' => 10,
        'remaining_mb' => 10,
    ];

    $failed = app(AcceptProviderWebhook::class)($provider, 'esim.usage', $payload, 'wh-missing');
    expect($failed->fresh()->status)->toBe(WebhookEventStatus::Failed);
    expect($failed->fresh()->last_error)->toStartWith('Dead letter');

    $retried = app(RetryWebhookEvent::class)($failed->fresh());
    expect($retried->status)->toBe(WebhookEventStatus::Failed);
    expect(EsimUsage::query()->count())->toBe(0);
});

it('keeps processed webhooks stable when the worker runs again', function (): void {
    [$profile, $provider] = provisionedFakeProfile();
    $payload = [
        'travora_fixture' => 'usage',
        'external_profile_id' => $profile->external_profile_id,
        'used_mb' => 50,
        'remaining_mb' => 974,
    ];

    $event = app(AcceptProviderWebhook::class)($provider, 'esim.usage', $payload, 'wh-replay-process');
    $again = app(ProcessProviderWebhook::class)($event->fresh());

    expect($again->status)->toBe(WebhookEventStatus::Processed);
    expect(EsimUsage::query()->where('esim_profile_id', $profile->id)->count())->toBe(1);
});
