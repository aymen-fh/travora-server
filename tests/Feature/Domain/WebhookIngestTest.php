<?php

declare(strict_types=1);

use App\Actions\Providers\IngestWebhookEvent;
use App\Actions\Providers\ProcessProviderWebhook;
use App\Enums\WebhookEventStatus;
use App\Models\Provider;

it('ingests a webhook once per provider event id', function (): void {
    $provider = Provider::factory()->create();
    $ingest = app(IngestWebhookEvent::class);

    $first = $ingest($provider, 'esim.usage', ['iccid' => '8901', 'used_mb' => 10], 'evt-1');
    $second = $ingest($provider, 'esim.usage', ['iccid' => '8901', 'used_mb' => 10], 'evt-1');

    expect($first->id)->toBe($second->id);
    expect($first->status)->toBe(WebhookEventStatus::Pending);
    expect($first->payload_hash)->toHaveLength(64);
});

it('replays the same payload hash even without an event id', function (): void {
    $provider = Provider::factory()->create();
    $ingest = app(IngestWebhookEvent::class);
    $payload = ['hello' => 'world'];

    $first = $ingest($provider, 'ping', $payload);
    $second = $ingest($provider, 'ping', $payload);

    expect($first->id)->toBe($second->id);
});

it('marks unknown provider payloads as ignored until mapping exists', function (): void {
    $provider = Provider::factory()->create();
    $event = app(IngestWebhookEvent::class)($provider, 'unknown', ['raw' => true], 'evt-ignored');

    $processed = app(ProcessProviderWebhook::class)($event);

    expect($processed->status)->toBe(WebhookEventStatus::Ignored);
    expect($processed->processed_at)->not->toBeNull();
});
