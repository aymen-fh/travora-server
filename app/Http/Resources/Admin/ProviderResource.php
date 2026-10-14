<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Models\Provider;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Provider
 */
final class ProviderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'type' => $this->type?->value,
            'status' => $this->status?->value,
            'priority' => $this->priority,
            'health_status' => $this->health_status?->value,
            'health_checked_at' => $this->health_checked_at?->toIso8601String(),
            'credentials_configured' => (int) ($this->credentials_count ?? 0),
        ];
    }
}
