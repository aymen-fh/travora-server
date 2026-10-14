<?php

declare(strict_types=1);

namespace App\Queries\Catalog;

use App\Domain\Catalog\PublicEsimPlan;
use App\Enums\RecordStatus;
use App\Models\EsimPlan;
use App\Models\EsimPlanPrice;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class SearchPublicEsimPlans
{
    public function __construct(
        private readonly MapPublicEsimPlan $map,
    ) {}

    /**
     * @param  array{
     *     currency?: string,
     *     country_iso2?: string,
     *     region_code?: string,
     *     min_data_mb?: int,
     *     max_data_mb?: int,
     *     min_validity_days?: int,
     *     max_validity_days?: int,
     *     min_price_minor?: int,
     *     max_price_minor?: int,
     *     featured?: bool,
     *     q?: string
     * }  $filters
     * @return Collection<int, PublicEsimPlan>
     */
    public function __invoke(array $filters = []): Collection
    {
        $currency = strtoupper($filters['currency'] ?? 'LYD');
        $at = now();

        $plans = EsimPlan::query()
            ->where('status', RecordStatus::Active)
            ->whereHas('provider', fn ($query) => $query->where('status', RecordStatus::Active))
            ->whereHas('prices', fn ($query) => $this->currentPrice($query, $currency, $at))
            ->when(
                isset($filters['country_iso2']),
                fn ($query) => $query->whereHas(
                    'countries',
                    fn ($countries) => $countries->where('iso2', strtoupper((string) $filters['country_iso2']))->where('is_active', true),
                ),
            )
            ->when(
                isset($filters['region_code']),
                fn ($query) => $query->whereHas(
                    'countries.region',
                    fn ($region) => $region->where('code', strtoupper((string) $filters['region_code'])),
                ),
            )
            ->when(isset($filters['min_data_mb']), fn ($query) => $query->where('data_mb', '>=', (int) $filters['min_data_mb']))
            ->when(isset($filters['max_data_mb']), fn ($query) => $query->where('data_mb', '<=', (int) $filters['max_data_mb']))
            ->when(isset($filters['min_validity_days']), fn ($query) => $query->where('validity_days', '>=', (int) $filters['min_validity_days']))
            ->when(isset($filters['max_validity_days']), fn ($query) => $query->where('validity_days', '<=', (int) $filters['max_validity_days']))
            ->when(! empty($filters['featured']), fn ($query) => $query->where('is_featured', true))
            ->when(
                filled($filters['q'] ?? null),
                fn ($query) => $query->where('name', 'like', '%'.$filters['q'].'%'),
            )
            ->with(['countries', 'networks', 'prices'])
            ->orderByDesc('is_featured')
            ->orderBy('name')
            ->get();

        return $plans
            ->map(function (EsimPlan $plan) use ($currency, $at, $filters): ?PublicEsimPlan {
                $price = $plan->prices
                    ->first(fn (EsimPlanPrice $candidate): bool => $this->matchesCurrentPrice($candidate, $currency, $at));

                if ($price === null) {
                    return null;
                }

                if (isset($filters['min_price_minor']) && $price->price_minor < (int) $filters['min_price_minor']) {
                    return null;
                }

                if (isset($filters['max_price_minor']) && $price->price_minor > (int) $filters['max_price_minor']) {
                    return null;
                }

                return ($this->map)($plan, $price);
            })
            ->filter()
            ->values();
    }

    private function currentPrice($query, string $currency, Carbon $at): void
    {
        $query->where('currency', $currency)
            ->where('is_active', true)
            ->where(function ($window) use ($at): void {
                $window->whereNull('starts_at')->orWhere('starts_at', '<=', $at);
            })
            ->where(function ($window) use ($at): void {
                $window->whereNull('ends_at')->orWhere('ends_at', '>', $at);
            });
    }

    private function matchesCurrentPrice(EsimPlanPrice $price, string $currency, Carbon $at): bool
    {
        if (! $price->is_active || $price->currency !== $currency) {
            return false;
        }

        if ($price->starts_at !== null && $price->starts_at->gt($at)) {
            return false;
        }

        if ($price->ends_at !== null && $price->ends_at->lte($at)) {
            return false;
        }

        return true;
    }
}
