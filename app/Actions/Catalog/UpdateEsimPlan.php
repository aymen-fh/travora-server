<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Enums\RecordStatus;
use App\Exceptions\CatalogUnavailableException;
use App\Models\EsimPlan;
use Illuminate\Support\Facades\DB;

final class UpdateEsimPlan
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __invoke(EsimPlan $plan, array $data): EsimPlan
    {
        $unlimited = (bool) ($data['is_unlimited'] ?? $plan->is_unlimited);
        $dataMb = array_key_exists('data_mb', $data) ? $data['data_mb'] : $plan->data_mb;

        if (! $unlimited && $dataMb === null) {
            throw new CatalogUnavailableException('Finite plans require data_mb.');
        }

        return DB::transaction(function () use ($plan, $data, $unlimited, $dataMb): EsimPlan {
            if (isset($data['name'])) {
                $plan->name = $data['name'];
            }

            if (array_key_exists('description', $data)) {
                $plan->description = $data['description'];
            }

            $plan->is_unlimited = $unlimited;
            $plan->data_mb = $unlimited ? ($dataMb !== null ? (int) $dataMb : null) : (int) $dataMb;

            if (isset($data['validity_days'])) {
                $plan->validity_days = (int) $data['validity_days'];
            }

            if (isset($data['hotspot_supported'])) {
                $plan->hotspot_supported = (bool) $data['hotspot_supported'];
            }

            if (isset($data['auto_topup_supported'])) {
                $plan->auto_topup_supported = (bool) $data['auto_topup_supported'];
            }

            if (isset($data['is_featured'])) {
                $plan->is_featured = (bool) $data['is_featured'];
            }

            if (isset($data['status'])) {
                $plan->status = $data['status'] instanceof RecordStatus
                    ? $data['status']
                    : RecordStatus::from((string) $data['status']);
            }

            $plan->save();

            return $plan;
        });
    }
}
