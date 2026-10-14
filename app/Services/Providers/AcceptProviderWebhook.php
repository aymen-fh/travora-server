<?php

declare(strict_types=1);

namespace App\Services\Providers;

use App\Actions\Providers\IngestWebhookEvent;
use App\Integrations\Esim\Support\WebhookSignatureGuard;
use App\Jobs\ProcessProviderWebhookJob;
use App\Models\Provider;
use App\Models\WebhookEvent;
use App\Enums\WebhookEventStatus;

final class AcceptProviderWebhook
{
    public function __construct(
        private readonly IngestWebhookEvent $ingest,
        private readonly WebhookSignatureGuard $signatures,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __invoke(
        Provider $provider,
        string $eventType,
        array $payload,
        ?string $eventId = null,
        ?string $signature = null,
    ): WebhookEvent {
        $eventId = $this->eventId($eventId, $payload);
        $valid = $this->signatures->verify($provider, $signature, $payload);
        $event = ($this->ingest)($provider, $eventType, $payload, $eventId, $signature, $valid);

        if ($event->status === WebhookEventStatus::Pending && $event->attempt_count === 0) {
            ProcessProviderWebhookJob::dispatch($event->id);
        }

        return $event;
    }

    /**
     * Map an inbound identifier onto webhook_events.event_id.
     * Official eSIMAccess envelopes use notifyId; existing callers may pass event_id or id.
     *
     * @param  array<string, mixed>  $payload
     */
    private function eventId(?string $eventId, array $payload): ?string
    {
        if (is_string($eventId) && $eventId !== '') {
            return $eventId;
        }

        foreach (['event_id', 'id', 'notifyId'] as $key) {
            $value = $payload[$key] ?? null;
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }
}
