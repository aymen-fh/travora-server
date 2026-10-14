<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Enums\RecordStatus;
use App\Exceptions\CatalogUnavailableException;
use App\Models\EsimPlan;
use App\Models\Provider;
use Illuminate\Support\Facades\DB;

final class CreateEsimPlan
{
    /**
     * @param  array{
     *     name: string,
     *     description?: string|null,
     *     data_mb?: int|null,
     *     is_unlimited?: bool,
     *     validity_days: int,
     *     hotspot_supported?: bool,
     *     auto_topup_supported?: bool,
     *     is_featured?: bool,
     *     status?: RecordStatus|string,
     *     provider_product_id?: string|null
     * }  $data
     */
    public function __invoke(Provider $provider, array $data): EsimPlan
    {
        $unlimited = (bool) ($data['is_unlimited'] ?? false);
        $dataMb = $data['data_mb'] ?? null;

        if (! $unlimited && $dataMb === null) {
            throw new CatalogUnavailableException('Finite plans require data_mb.');
        }

        return DB::transaction(function () use ($provider, $data, $unlimited, $dataMb): EsimPlan {
            $plan = new EsimPlan;
            $plan->provider_id = $provider->id;
            $plan->provider_product_id = $data['provider_product_id'] ?? null;
            $plan->name = $data['name'];
            $plan->description = $data['description'] ?? null;
            $plan->data_mb = $unlimited ? $dataMb : (int) $dataMb;
            $plan->is_unlimited = $unlimited;
            $plan->validity_days = (int) $data['validity_days'];
            $plan->hotspot_supported = (bool) ($data['hotspot_supported'] ?? false);
            $plan->auto_topup_supported = (bool) ($data['auto_topup_supported'] ?? false);
            $plan->is_featured = (bool) ($data['is_featured'] ?? false);
            $plan->status = $data['status'] ?? RecordStatus::Active;
            $plan->save();

            return $plan;
        });
    }
}
