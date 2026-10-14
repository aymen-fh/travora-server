<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use App\Enums\ProviderCapability;
use App\Enums\RecordStatus;
use App\Integrations\Esim\DTOs\ProviderCoverageDTO;
use App\Integrations\Esim\DTOs\ProviderPlanDTO;
use App\Integrations\Esim\Manager\ProviderManager;
use App\Models\Country;
use App\Models\EsimPlan;
use App\Models\Provider;
use App\Models\ProviderCountryCoverage;
use App\Models\ProviderExternalReference;
use App\Models\ProviderProduct;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class ImportProviderCatalog
{
    public function __construct(
        private readonly ProviderManager $providers,
    ) {}

    /**
     * @return array{imported: int, updated: int, disabled: int, plans: int}
     */
    public function __invoke(Provider $provider): array
    {
        @set_time_limit(300);
        @ini_set('max_execution_time', '300');

        $driver = $this->providers->driver($provider);
        $this->providers->assertCapability($driver, ProviderCapability::Plans);

        $remote = $driver->getPlans();
        $coverage = $driver->capabilities()->supports(ProviderCapability::Coverage)
            ? $driver->getCoverage()
            : [];

        /** @var array<string, string> $countryMap */
        $countryMap = Country::query()->pluck('id', 'iso2')
            ->mapWithKeys(fn ($id, $iso) => [strtoupper((string) $iso) => (string) $id])
            ->all();

        return DB::transaction(function () use ($provider, $remote, $coverage, $countryMap): array {
            $this->syncCoverage($provider, $coverage, $countryMap);

            $seen = [];
            $imported = 0;
            $updated = 0;
            $plans = 0;

            /** @var Collection<string, ProviderProduct> $existingProducts */
            $existingProducts = ProviderProduct::query()
                ->where('provider_id', $provider->id)
                ->where('product_type', 'esim')
                ->get()
                ->keyBy('external_product_id');

            /** @var Collection<string, EsimPlan> $existingPlans */
            $existingPlans = EsimPlan::query()
                ->where('provider_id', $provider->id)
                ->whereNotNull('provider_product_id')
                ->get()
                ->keyBy('provider_product_id');

            /** @var Collection<string, ProviderExternalReference> $existingRefs */
            $existingRefs = ProviderExternalReference::query()
                ->where('provider_id', $provider->id)
                ->where('entity_type', 'esim_plan')
                ->get()
                ->keyBy('external_id');

            foreach ($remote as $dto) {
                $seen[] = $dto->externalPlanId;
                [$product, $wasNew] = $this->upsertProduct($provider, $dto, $existingProducts);
                $wasNew ? $imported++ : $updated++;
                $this->upsertCanonicalPlan($provider, $product, $dto, $existingPlans, $existingRefs, $countryMap);
                $plans++;
            }

            $disabled = $this->disableMissing($provider, $seen);

            return compact('imported', 'updated', 'disabled', 'plans');
        });
    }

    /**
     * @param  list<ProviderCoverageDTO>  $coverage
     * @param  array<string, string>  $countryMap
     */
    private function syncCoverage(Provider $provider, array $coverage, array $countryMap): void
    {
        $existing = ProviderCountryCoverage::query()
            ->where('provider_id', $provider->id)
            ->where('service_type', 'esim')
            ->pluck('id', 'country_id')
            ->all();

        foreach ($coverage as $item) {
            $iso = strtoupper($item->iso2);
            $countryId = $countryMap[$iso] ?? null;
            if ($countryId === null) {
                continue;
            }

            if (! isset($existing[$countryId])) {
                $row = new ProviderCountryCoverage;
                $row->provider_id = $provider->id;
                $row->country_id = $countryId;
                $row->service_type = 'esim';
                $row->status = RecordStatus::Active;
                $row->save();
                $existing[$countryId] = $row->id;
            }
        }
    }

    /**
     * @param  Collection<string, ProviderProduct>  $existingProducts
     * @return array{0: ProviderProduct, 1: bool}
     */
    private function upsertProduct(Provider $provider, ProviderPlanDTO $dto, Collection $existingProducts): array
    {
        $product = $existingProducts->get($dto->externalPlanId);
        $wasNew = $product === null;

        if ($product === null) {
            $product = new ProviderProduct;
            $product->provider_id = $provider->id;
            $product->product_type = 'esim';
            $product->external_product_id = $dto->externalPlanId;
            $existingProducts->put($dto->externalPlanId, $product);
        }

        $product->name = $dto->name;
        $product->status = RecordStatus::Active;
        $product->raw_data = [
            'cost_minor' => $dto->costMinor,
            'currency' => $dto->currency,
            'data_mb' => $dto->dataMb,
            'validity_days' => $dto->validityDays,
        ];
        $product->synced_at = now();
        $product->save();

        return [$product, $wasNew];
    }

    /**
     * @param  Collection<string, EsimPlan>  $existingPlans
     * @param  Collection<string, ProviderExternalReference>  $existingRefs
     * @param  array<string, string>  $countryMap
     */
    private function upsertCanonicalPlan(
        Provider $provider,
        ProviderProduct $product,
        ProviderPlanDTO $dto,
        Collection $existingPlans,
        Collection $existingRefs,
        array $countryMap,
    ): void {
        $plan = $existingPlans->get($product->id);

        if ($plan === null) {
            $ref = $existingRefs->get($dto->externalPlanId);
            if ($ref !== null) {
                $plan = EsimPlan::query()->find($ref->entity_id);
            }
        }

        if ($plan === null) {
            $plan = new EsimPlan;
            $plan->provider_id = $provider->id;
            $plan->name = $dto->name;
            $plan->status = RecordStatus::Active;
        }

        $plan->provider_product_id = $product->id;
        $plan->description = $dto->description;
        $plan->data_mb = $dto->isUnlimited ? $dto->dataMb : $dto->dataMb;
        $plan->is_unlimited = $dto->isUnlimited;
        $plan->validity_days = $dto->validityDays;
        $plan->save();

        $existingPlans->put($product->id, $plan);

        // Reference
        $ref = $existingRefs->get($dto->externalPlanId);
        if ($ref === null) {
            $ref = new ProviderExternalReference;
            $ref->provider_id = $provider->id;
            $ref->entity_type = 'esim_plan';
            $ref->external_id = $dto->externalPlanId;
            $ref->entity_id = $plan->id;
            $ref->external_type = 'plan';
            $ref->save();
            $existingRefs->put($dto->externalPlanId, $ref);
        }

        // Sync Countries
        $countryIds = [];
        foreach ($dto->countryIso2 as $iso) {
            $upper = strtoupper($iso);
            if (isset($countryMap[$upper])) {
                $countryIds[] = $countryMap[$upper];
            }
        }
        if ($countryIds !== []) {
            $plan->countries()->sync($countryIds);
        }
    }

    /**
     * @param  list<string>  $seenExternalIds
     */
    private function disableMissing(Provider $provider, array $seenExternalIds): int
    {
        $stale = ProviderProduct::query()
            ->where('provider_id', $provider->id)
            ->where('product_type', 'esim')
            ->where('status', RecordStatus::Active)
            ->when(
                $seenExternalIds !== [],
                fn ($query) => $query->whereNotIn('external_product_id', $seenExternalIds),
                fn ($query) => $query->whereRaw('1 = 1'),
            )
            ->get();

        foreach ($stale as $product) {
            $product->status = RecordStatus::Inactive;
            $product->save();

            EsimPlan::query()
                ->where('provider_product_id', $product->id)
                ->where('status', RecordStatus::Active)
                ->update(['status' => RecordStatus::Inactive->value]);
        }

        return $stale->count();
    }
}
