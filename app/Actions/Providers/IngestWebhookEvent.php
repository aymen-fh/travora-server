<?php

declare(strict_types=1);

namespace App\Actions\Providers;

use App\Enums\WebhookEventStatus;
use App\Models\Provider;
use App\Models\WebhookEvent;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final class IngestWebhookEvent
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __invoke(
        Provider $provider,
        string $eventType,
        array $payload,
        ?string $eventId = null,
        ?string $signature = null,
        bool $signatureValid = false,
    ): WebhookEvent {
        $payloadHash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));

        if ($eventId !== null) {
            $existing = WebhookEvent::query()
                ->where('provider_id', $provider->id)
                ->where('event_id', $eventId)
                ->first();

            if ($existing !== null) {
                return $existing;
            }
        }

        $existingHash = WebhookEvent::query()
            ->where('provider_id', $provider->id)
            ->where('payload_hash', $payloadHash)
            ->first();

        if ($existingHash !== null) {
            return $existingHash;
        }

        try {
            return DB::transaction(function () use ($provider, $eventType, $payload, $eventId, $signature, $signatureValid, $payloadHash): WebhookEvent {
                $event = new WebhookEvent;
                $event->provider_id = $provider->id;
                $event->event_id = $eventId;
                $event->event_type = $eventType;
                $event->signature = $signature;
                $event->payload = $payload;
                $event->payload_hash = $payloadHash;
                $event->signature_valid = $signatureValid;
                $event->status = WebhookEventStatus::Pending;
                $event->attempt_count = 0;
                $event->received_at = now();
                $event->save();

                return $event;
            });
        } catch (QueryException $exception) {
            $replayed = WebhookEvent::query()
                ->where('provider_id', $provider->id)
                ->where(function ($query) use ($eventId, $payloadHash): void {
                    $query->where('payload_hash', $payloadHash);
                    if ($eventId !== null) {
                        $query->orWhere('event_id', $eventId);
                    }
                })
                ->first();

            if ($replayed !== null) {
                return $replayed;
            }

            throw $exception;
        }
    }
}
