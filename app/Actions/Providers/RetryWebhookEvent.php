<?php

declare(strict_types=1);

namespace App\Actions\Providers;

use App\Enums\WebhookEventStatus;
use App\Jobs\ProcessProviderWebhookJob;
use App\Models\WebhookEvent;
use App\States\WebhookEventStateMachine;

final class RetryWebhookEvent
{
    public function __invoke(WebhookEvent $event): WebhookEvent
    {
        if ($event->status !== WebhookEventStatus::Failed) {
            return $event;
        }

        WebhookEventStateMachine::assert($event->status, WebhookEventStatus::Pending);
        $event->status = WebhookEventStatus::Pending;
        $event->save();

        ProcessProviderWebhookJob::dispatch($event->id);

        return $event->fresh() ?? $event;
    }
}
