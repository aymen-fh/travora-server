<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Enums\RecordStatus;
use App\Models\EsimPlan;
use App\Models\ProviderProduct;
use Illuminate\Support\Facades\DB;

final class MapProviderProductToPlan
{
    public function __invoke(EsimPlan $plan, ProviderProduct $product): EsimPlan
    {
        if ($plan->provider_id !== $product->provider_id) {
            $plan->provider_id = $product->provider_id;
        }

        return DB::transaction(function () use ($plan, $product): EsimPlan {
            $plan->provider_product_id = $product->id;
            $plan->save();

            if ($product->status !== RecordStatus::Active && $plan->status === RecordStatus::Active) {
                $plan->status = RecordStatus::Inactive;
                $plan->save();
            }

            return $plan;
        });
    }
}
