<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Models\Country;
use App\Models\EsimPlan;
use Illuminate\Support\Facades\DB;

final class SyncPlanCountries
{
    /**
     * @param  list<string>  $countryIds
     */
    public function __invoke(EsimPlan $plan, array $countryIds): EsimPlan
    {
        $ids = Country::query()->whereIn('id', $countryIds)->pluck('id')->all();

        DB::transaction(function () use ($plan, $ids): void {
            $plan->countries()->sync($ids);
        });

        return $plan->load('countries');
    }
}
