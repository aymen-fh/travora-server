<?php

declare(strict_types=1);

namespace App\Actions\Providers;

use App\Actions\Esim\RecordEsimUsage;
use App\Enums\ProviderCapability;
use App\Enums\WebhookEventStatus;
use App\Integrations\Esim\DTOs\ProviderInboundEvent;
use App\Integrations\Esim\Manager\ProviderManager;
use App\Integrations\Esim\Support\SecretMasker;
use App\Models\EsimProfile;
use App\Models\WebhookEvent;
use App\States\WebhookEventStateMachine;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

final class ProcessProviderWebhook
{
    public function __construct(
        private readonly ProviderManager $providers,
        private readonly RecordEsimUsage $recordUsage,
    ) {}

    public function __invoke(WebhookEvent $event): WebhookEvent
    {
        if (in_array($event->status, [WebhookEventStatus::Processed, WebhookEventStatus::Ignored], true)) {
            return $event;
        }

        if ($event->status === WebhookEventStatus::Failed) {
            return $event;
        }

        return DB::transaction(function () use ($event): WebhookEvent {
            $locked = WebhookEvent::query()->whereKey($event->id)->lockForUpdate()->firstOrFail();

            if (in_array($locked->status, [WebhookEventStatus::Processed, WebhookEventStatus::Ignored], true)) {
                return $locked;
            }

            if ($locked->status === WebhookEventStatus::Failed) {
                return $locked;
            }

            if ($locked->status === WebhookEventStatus::Pending) {
                WebhookEventStateMachine::assert($locked->status, WebhookEventStatus::Processing);
                $locked->status = WebhookEventStatus::Processing;
            }

            $locked->attempt_count++;
            $locked->save();

            try {
                return $this->apply($locked);
            } catch (Throwable $exception) {
                return $this->fail($locked, $exception->getMessage());
            }
        });
    }

    private function apply(WebhookEvent $event): WebhookEvent
    {
        if (! $event->signature_valid) {
            return $this->ignore($event, 'Webhook signature was not valid.');
        }

        $event->loadMissing('provider');
        $provider = $event->provider;

        if ($provider === null) {
            return $this->ignore($event, 'Webhook provider is missing.');
        }

        try {
            $driver = $this->providers->driver($provider);
        } catch (InvalidArgumentException $exception) {
            return $this->ignore($event, $exception->getMessage());
        }

        if (! $driver->capabilities()->supports(ProviderCapability::Webhooks)) {
            return $this->ignore($event, 'Provider does not advertise webhook mapping.');
        }

        $inbound = $driver->interpretInboundEvent($event->payload ?? []);

        if ($inbound === null) {
            $notifyType = (string) ($event->payload['notifyType'] ?? $event->payload['type'] ?? '');
            if (in_array(strtoupper($notifyType), ['CHECK_HEALTH', 'HEALTH_CHECK'], true)) {
                return $this->ignore($event, 'Provider health check event acknowledged without business logic execution.');
            }

            return $this->ignore($event, 'Payload mapping is unknown for this driver.');
        }

        if ($inbound->type === 'usage') {
            return $this->applyUsage($event, $inbound);
        }

        return $this->ignore($event, 'Inbound event type is not handled.');
    }

    private function applyUsage(WebhookEvent $event, ProviderInboundEvent $inbound): WebhookEvent
    {
        if ($inbound->externalProfileId === null || $inbound->externalProfileId === '' || $inbound->usage === null) {
            return $this->ignore($event, 'Usage event is missing a profile or snapshot.');
        }

        $profile = EsimProfile::query()
            ->where('provider_id', $event->provider_id)
            ->where('external_profile_id', $inbound->externalProfileId)
            ->first();

        if ($profile === null) {
            return $this->fail($event, 'eSIM profile not found for inbound usage event.');
        }

        ($this->recordUsage)($profile, $inbound->usage);

        WebhookEventStateMachine::assert($event->status, WebhookEventStatus::Processed);
        $event->status = WebhookEventStatus::Processed;
        $event->processed_at = now();
        $event->last_error = null;
        $event->save();

        return $event;
    }

    private function ignore(WebhookEvent $event, string $reason): WebhookEvent
    {
        WebhookEventStateMachine::assert($event->status, WebhookEventStatus::Ignored);
        $event->status = WebhookEventStatus::Ignored;
        $event->processed_at = now();
        $event->last_error = $reason;
        $event->save();

        return $event;
    }

    private function fail(WebhookEvent $event, string $reason): WebhookEvent
    {
        $maxAttempts = max(1, (int) config('esim.webhook_max_attempts', 8));
        $masked = SecretMasker::mask(['error' => $reason]);
        $message = (string) ($masked['error'] ?? $reason);

        if ($event->attempt_count >= $maxAttempts) {
            WebhookEventStateMachine::assert($event->status, WebhookEventStatus::Failed);
            $event->status = WebhookEventStatus::Failed;
            $event->last_error = 'Dead letter after '.$event->attempt_count.' attempts: '.$message;
            $event->save();

            return $event;
        }

        WebhookEventStateMachine::assert($event->status, WebhookEventStatus::Pending);
        $event->status = WebhookEventStatus::Pending;
        $event->last_error = $message;
        $event->save();

        return $event;
    }
}
