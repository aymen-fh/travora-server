<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Models\EsimPlan;
use App\Models\NetworkOperator;
use Illuminate\Support\Facades\DB;

final class SyncPlanNetworks
{
    /**
     * @param  list<string>  $networkIds
     */
    public function __invoke(EsimPlan $plan, array $networkIds): EsimPlan
    {
        $ids = NetworkOperator::query()->whereIn('id', $networkIds)->pluck('id')->all();

        DB::transaction(function () use ($plan, $ids): void {
            $plan->networks()->sync($ids);
        });

        return $plan->load('networks');
    }
}
