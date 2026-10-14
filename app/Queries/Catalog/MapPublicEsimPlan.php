<?php

declare(strict_types=1);

namespace App\Queries\Catalog;

use App\Domain\Catalog\PublicEsimPlan;
use App\Models\EsimPlan;
use App\Models\EsimPlanPrice;

final class MapPublicEsimPlan
{
    public function __invoke(EsimPlan $plan, EsimPlanPrice $price): PublicEsimPlan
    {
        $plan->loadMissing(['countries', 'networks']);

        return new PublicEsimPlan(
            id: $plan->id,
            name: $plan->name,
            description: $plan->description,
            dataMb: $plan->data_mb,
            isUnlimited: $plan->is_unlimited,
            validityDays: $plan->validity_days,
            hotspotSupported: $plan->hotspot_supported,
            isFeatured: $plan->is_featured,
            currency: $price->currency,
            priceMinor: $price->price_minor,
            countries: $plan->countries
                ->map(fn ($country): array => [
                    'iso2' => $country->iso2,
                    'name' => $country->name,
                ])
                ->values()
                ->all(),
            networks: $plan->networks
                ->map(fn ($network): array => [
                    'name' => $network->name,
                ])
                ->values()
                ->all(),
        );
    }
}
