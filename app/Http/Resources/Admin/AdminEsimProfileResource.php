<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Models\EsimProfile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin EsimProfile
 */
final class AdminEsimProfileResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'plan_id' => $this->plan_id,
            'provider_id' => $this->provider_id,
            'status' => $this->status?->value,
            'iccid' => $this->iccid,
            'external_profile_id' => $this->external_profile_id,
            'data_total_mb' => $this->data_total_mb,
            'data_remaining_mb' => $this->data_remaining_mb,
            'valid_from' => $this->valid_from?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'plan_name' => $this->whenLoaded('plan', fn () => $this->plan?->name),
            'provider_name' => $this->whenLoaded('provider', fn () => $this->provider?->name),
            'enabled_services' => $this->when(
                $this->relationLoaded('provider') && $this->provider?->relationLoaded('services'),
                fn () => ($this->provider?->services ?? collect())->where('is_enabled', true)->pluck('service_type')->values()->all(),
            ),
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user?->id,
                'name' => $this->user?->profile?->display_name,
                'email' => $this->user?->email,
            ]),
        ];
    }
}
