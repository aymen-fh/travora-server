<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\EsimProfile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin EsimProfile
 */
final class EsimResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'plan_id' => $this->plan_id,
            'status' => $this->status?->value,
            'iccid' => $this->iccid,
            'data_total_mb' => $this->data_total_mb,
            'data_remaining_mb' => $this->data_remaining_mb,
            'valid_from' => $this->valid_from?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
        ];
    }
}
