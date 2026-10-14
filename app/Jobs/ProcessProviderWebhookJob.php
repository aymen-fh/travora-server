<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Providers\ProcessProviderWebhook;
use App\Enums\WebhookEventStatus;
use App\Models\WebhookEvent;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class ProcessProviderWebhookJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $backoff = 30;

    public function tries(): int
    {
        return max(1, (int) config('esim.webhook_max_attempts', 8));
    }

    public function __construct(
        public readonly string $webhookEventId,
    ) {}

    public function uniqueId(): string
    {
        return 'webhook-'.$this->webhookEventId;
    }

    public function handle(ProcessProviderWebhook $process): void
    {
        $event = WebhookEvent::query()->findOrFail($this->webhookEventId);
        $event = $process($event);

        if ($event->status === WebhookEventStatus::Pending && $event->attempt_count < $this->tries()) {
            $this->release($this->backoff);
        }
    }
}
