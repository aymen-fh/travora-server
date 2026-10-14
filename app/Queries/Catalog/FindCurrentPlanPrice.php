<?php

declare(strict_types=1);

namespace App\Queries\Catalog;

use App\Models\EsimPlan;
use App\Models\EsimPlanPrice;
use Illuminate\Support\Carbon;

final class FindCurrentPlanPrice
{
    public function __invoke(EsimPlan $plan, string $currency, ?Carbon $at = null): ?EsimPlanPrice
    {
        $at ??= now();
        $currency = strtoupper($currency);

        return EsimPlanPrice::query()
            ->where('esim_plan_id', $plan->id)
            ->where('currency', $currency)
            ->where('is_active', true)
            ->where(function ($query) use ($at): void {
                $query->whereNull('starts_at')->orWhere('starts_at', '<=', $at);
            })
            ->where(function ($query) use ($at): void {
                $query->whereNull('ends_at')->orWhere('ends_at', '>', $at);
            })
            ->orderByDesc('created_at')
            ->first();
    }
}
