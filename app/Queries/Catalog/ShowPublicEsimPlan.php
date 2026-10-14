<?php

declare(strict_types=1);

namespace App\Queries\Catalog;

use App\Domain\Catalog\PublicEsimPlan;
use App\Enums\RecordStatus;
use App\Exceptions\CatalogUnavailableException;
use App\Models\EsimPlan;

final class ShowPublicEsimPlan
{
    public function __construct(
        private readonly FindCurrentPlanPrice $currentPrice,
        private readonly MapPublicEsimPlan $map,
    ) {}

    public function __invoke(EsimPlan $plan, string $currency = 'LYD'): PublicEsimPlan
    {
        $plan->loadMissing('provider');

        if ($plan->status !== RecordStatus::Active || $plan->provider?->status !== RecordStatus::Active) {
            throw new CatalogUnavailableException('eSIM plan is not available.');
        }

        $price = ($this->currentPrice)($plan, $currency);

        if ($price === null) {
            throw new CatalogUnavailableException('No active price exists for this plan.');
        }

        return ($this->map)($plan, $price);
    }
}
