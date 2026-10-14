<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Models\WebhookEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin WebhookEvent
 */
final class AdminWebhookEventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'provider_id' => $this->provider_id,
            'event_id' => $this->event_id,
            'event_type' => $this->event_type,
            'status' => $this->status?->value,
            'signature_valid' => $this->signature_valid,
            'attempt_count' => $this->attempt_count,
            'last_error' => $this->last_error,
            'received_at' => $this->received_at?->toIso8601String(),
            'processed_at' => $this->processed_at?->toIso8601String(),
            'payload' => $this->maskedPayload(),
        ];
    }
}
