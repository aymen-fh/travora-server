<?php

declare(strict_types=1);

use App\Enums\WebhookEventStatus;
use App\Models\Provider;
use App\Models\WebhookEvent;
use App\Services\Providers\AcceptProviderWebhook;
use Illuminate\Support\Facades\Event;

function esimAccessProvider(): Provider
{
    return Provider::factory()->esimAccess()->create();
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function officialUsageEnvelope(array $overrides = []): array
{
    return array_replace_recursive([
        'notifyType' => 'DATA_USAGE',
        'notifyId' => '7cb37a1e9ed2468e9243e6ccae682125',
        'eventGenerateTime' => '2026-04-22T17:21:59Z',
        'content' => [
            'esimTranNo' => '26041615170028',
            'orderUsage' => 10741066164,
            'remain' => 10733770316,
        ],
    ], $overrides);
}

it('stores official notifyId as the webhook event identifier', function (): void {
    Event::fake();
    $provider = esimAccessProvider();
    $payload = officialUsageEnvelope();

    $this->postJson('/api/v1/webhooks/providers/esimaccess', $payload)
        ->assertAccepted();

    $event = WebhookEvent::query()->where('provider_id', $provider->id)->firstOrFail();

    expect($event->event_id)->toBe('7cb37a1e9ed2468e9243e6ccae682125');
    expect($event->signature_valid)->toBeTrue();
});

it('ingests a payload without notifyId and leaves event_id empty', function (): void {
    Event::fake();
    $provider = esimAccessProvider();

    $this->postJson('/api/v1/webhooks/providers/esimaccess', [
        'notifyType' => 'DATA_USAGE',
        'content' => ['esimTranNo' => '26041615170028'],
    ])->assertAccepted();

    $event = WebhookEvent::query()->where('provider_id', $provider->id)->firstOrFail();

    expect($event->event_id)->toBeNull();
    expect($event->payload_hash)->not->toBeEmpty();
    expect($event->signature_valid)->toBeFalse();
    expect($event->status)->toBe(WebhookEventStatus::Ignored);
});

it('replays the same official payload once via notifyId and payload_hash', function (): void {
    Event::fake();
    $provider = esimAccessProvider();
    $payload = officialUsageEnvelope();
    $accept = app(AcceptProviderWebhook::class);

    $first = $accept($provider, 'DATA_USAGE', $payload);
    $second = $accept($provider, 'DATA_USAGE', $payload);

    expect($second->id)->toBe($first->id);
    expect(WebhookEvent::query()->where('provider_id', $provider->id)->count())->toBe(1);
    expect(WebhookEvent::query()->where('event_id', '7cb37a1e9ed2468e9243e6ccae682125')->count())->toBe(1);
});

it('stores distinct notifyId values as separate webhook events', function (): void {
    Event::fake();
    $provider = esimAccessProvider();
    $accept = app(AcceptProviderWebhook::class);

    $first = $accept($provider, 'DATA_USAGE', officialUsageEnvelope([
        'notifyId' => 'notify-one',
    ]));
    $second = $accept($provider, 'DATA_USAGE', officialUsageEnvelope([
        'notifyId' => 'notify-two',
        'content' => ['orderUsage' => 1],
    ]));

    expect($second->id)->not->toBe($first->id);
    expect($first->event_id)->toBe('notify-one');
    expect($second->event_id)->toBe('notify-two');
    expect(WebhookEvent::query()->where('provider_id', $provider->id)->count())->toBe(2);
});
