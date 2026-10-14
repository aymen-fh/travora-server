<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Models\ProviderService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ProviderService
 */
final class AdminProviderServiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'provider_id' => $this->provider_id,
            'service_type' => $this->service_type,
            'is_enabled' => $this->is_enabled,
            'priority' => $this->priority,
        ];
    }
}
