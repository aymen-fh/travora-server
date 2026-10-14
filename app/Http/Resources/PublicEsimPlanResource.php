<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Catalog\PublicEsimPlan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PublicEsimPlan
 */
final class PublicEsimPlanResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var PublicEsimPlan $plan */
        $plan = $this->resource;

        return [
            'id' => $plan->id,
            'name' => $plan->name,
            'description' => $plan->description,
            'data_mb' => $plan->dataMb,
            'is_unlimited' => $plan->isUnlimited,
            'validity_days' => $plan->validityDays,
            'hotspot_supported' => $plan->hotspotSupported,
            'is_featured' => $plan->isFeatured,
            'currency' => $plan->currency,
            'price_minor' => $plan->priceMinor,
            'countries' => $plan->countries,
            'networks' => $plan->networks,
        ];
    }
}
