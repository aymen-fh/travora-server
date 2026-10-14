<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Enums\RecordStatus;
use App\Exceptions\CatalogUnavailableException;
use App\Models\EsimPlan;
use App\Models\EsimPlanPrice;
use App\Queries\Catalog\FindCurrentPlanPrice;

final class QuoteEsimPlan
{
    public function __construct(
        private readonly FindCurrentPlanPrice $currentPrice,
    ) {}

    public function __invoke(EsimPlan $plan, string $currency = 'LYD'): EsimPlanPrice
    {
        $plan->loadMissing('provider');

        if ($plan->status !== RecordStatus::Active) {
            throw new CatalogUnavailableException('eSIM plan is not active.');
        }

        if ($plan->provider === null || $plan->provider->status !== RecordStatus::Active) {
            throw new CatalogUnavailableException('Provider is not active.');
        }

        $price = ($this->currentPrice)($plan, $currency);

        if ($price === null) {
            throw new CatalogUnavailableException('No active price exists for this plan.');
        }

        return $price;
    }
}
