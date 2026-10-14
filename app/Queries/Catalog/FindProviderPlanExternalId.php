<?php

declare(strict_types=1);

namespace App\Queries\Catalog;

use App\Models\EsimPlan;
use App\Models\ProviderExternalReference;

final class FindProviderPlanExternalId
{
    public function __invoke(EsimPlan $plan): ?string
    {
        $reference = ProviderExternalReference::query()
            ->where('provider_id', $plan->provider_id)
            ->where('entity_type', 'esim_plan')
            ->where('entity_id', $plan->id)
            ->first();

        if ($reference !== null && $reference->external_id !== '') {
            return $reference->external_id;
        }

        $plan->loadMissing('providerProduct');
        $externalProductId = $plan->providerProduct?->external_product_id;

        return is_string($externalProductId) && $externalProductId !== '' ? $externalProductId : null;
    }
}
