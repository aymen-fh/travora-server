<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Models\ProviderCountryCoverage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ProviderCountryCoverage
 */
final class AdminProviderCoverageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'provider_id' => $this->provider_id,
            'country_id' => $this->country_id,
            'service_type' => $this->service_type,
            'status' => $this->status?->value,
            'country' => $this->whenLoaded('country', fn () => [
                'id' => $this->country?->id,
                'name' => $this->country?->name,
                'name_ar' => $this->country?->name_ar,
                'iso2' => $this->country?->iso2,
            ]),
        ];
    }
}
