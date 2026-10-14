<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Audit\RecordAuditLog;
use App\Actions\Catalog\CreateEsimPlan;
use App\Actions\Catalog\DeactivateEsimPlanPrice;
use App\Actions\Catalog\SetEsimPlanPrice;
use App\Actions\Catalog\SyncPlanCountries;
use App\Actions\Catalog\SyncPlanNetworks;
use App\Actions\Catalog\UpdateEsimPlan;
use App\Domain\MoneyMinor;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreEsimPlanPriceRequest;
use App\Http\Requests\Admin\StoreEsimPlanRequest;
use App\Http\Requests\Admin\SyncPlanCountriesRequest;
use App\Http\Requests\Admin\SyncPlanNetworksRequest;
use App\Http\Requests\Admin\UpdateEsimPlanRequest;
use App\Http\Resources\Admin\AdminEsimPlanResource;
use App\Http\Responses\ApiResponse;
use App\Integrations\Esim\Manager\ProviderManager;
use App\Models\EsimPlan;
use App\Models\EsimPlanPrice;
use App\Models\Provider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CatalogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', EsimPlan::class);

        $perPage = min(100, max(1, $request->integer('per_page', 20)));

        $page = EsimPlan::query()
            ->with(['provider', 'providerProduct', 'prices', 'countries', 'networks'])
            ->when($request->string('search')->toString(), fn ($query, string $search) => $query->where('name', 'like', '%'.$search.'%'))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('provider_id'), fn ($query) => $query->where('provider_id', $request->string('provider_id')))
            ->latest()
            ->paginate($perPage);

        return ApiResponse::success(
            AdminEsimPlanResource::collection($page->getCollection())->resolve(),
            'OK',
            200,
            [
                'current_page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
            ],
        );
    }

    public function store(StoreEsimPlanRequest $request, CreateEsimPlan $create, RecordAuditLog $audit): JsonResponse
    {
        $this->authorize('create', EsimPlan::class);

        $data = $request->validated();
        $provider = Provider::query()->findOrFail($data['provider_id']);
        unset($data['provider_id']);

        $plan = $create($provider, $data);
        $this->record($audit, $request, 'catalog.plan.created', $plan->id, ['name' => $plan->name]);

        return ApiResponse::created(new AdminEsimPlanResource($plan->load(['provider', 'providerProduct', 'prices', 'countries', 'networks'])));
    }

    public function show(EsimPlan $plan): JsonResponse
    {
        $this->authorize('view', $plan);

        return ApiResponse::success(new AdminEsimPlanResource($plan->load(['provider', 'providerProduct', 'prices', 'countries', 'networks'])));
    }

    public function update(UpdateEsimPlanRequest $request, EsimPlan $plan, UpdateEsimPlan $update, RecordAuditLog $audit): JsonResponse
    {
        $this->authorize('update', $plan);
        $updated = $update($plan, $request->validated());
        $this->record($audit, $request, 'catalog.plan.updated', $updated->id, ['name' => $updated->name]);

        return ApiResponse::success(new AdminEsimPlanResource($updated->load(['provider', 'providerProduct', 'prices', 'countries', 'networks'])));
    }

    public function prices(EsimPlan $plan): JsonResponse
    {
        $this->authorize('viewPrices', $plan);

        return ApiResponse::success(new AdminEsimPlanResource($plan->load('prices')));
    }

    public function storePrice(StoreEsimPlanPriceRequest $request, EsimPlan $plan, SetEsimPlanPrice $setPrice, RecordAuditLog $audit): JsonResponse
    {
        $this->authorize('managePrices', $plan);

        $setPrice(
            $plan,
            MoneyMinor::of((int) $request->integer('price_minor'), $request->string('currency')->toString()),
            $request->date('starts_at'),
            $request->date('ends_at'),
            $request->boolean('is_active', true),
        );
        $this->record($audit, $request, 'catalog.price.updated', $plan->id, [
            'currency' => $request->string('currency')->toString(),
            'price_minor' => $request->integer('price_minor'),
        ]);

        return ApiResponse::created(new AdminEsimPlanResource($plan->fresh(['prices', 'countries', 'networks'])));
    }

    public function deactivatePrice(Request $request, EsimPlanPrice $price, DeactivateEsimPlanPrice $deactivate, RecordAuditLog $audit): JsonResponse
    {
        $price->loadMissing('esimPlan');
        $this->authorize('managePrices', $price->esimPlan);

        $deactivate($price);
        $this->record($audit, $request, 'catalog.price.deactivated', $price->esim_plan_id, ['price_id' => $price->id]);

        return ApiResponse::success(new AdminEsimPlanResource($price->esimPlan()->with(['prices', 'countries', 'networks'])->firstOrFail()), 'Price deactivated');
    }

    public function syncCountries(SyncPlanCountriesRequest $request, EsimPlan $plan, SyncPlanCountries $sync, RecordAuditLog $audit): JsonResponse
    {
        $this->authorize('update', $plan);
        $synced = $sync($plan, $request->validated('country_ids'));
        $this->record($audit, $request, 'catalog.countries.synced', $plan->id, ['country_ids' => $request->validated('country_ids')]);

        return ApiResponse::success(new AdminEsimPlanResource($synced));
    }

    public function syncNetworks(SyncPlanNetworksRequest $request, EsimPlan $plan, SyncPlanNetworks $sync, RecordAuditLog $audit): JsonResponse
    {
        $this->authorize('update', $plan);
        $synced = $sync($plan, $request->validated('network_ids'));
        $this->record($audit, $request, 'catalog.networks.synced', $plan->id, ['network_ids' => $request->validated('network_ids')]);

        return ApiResponse::success(new AdminEsimPlanResource($synced));
    }

    public function vendorPackages(Request $request, ProviderController $providerController, ProviderManager $manager): JsonResponse
    {
        $this->authorize('viewAny', EsimPlan::class);

        $providerId = $request->string('provider_id')->toString();
        $provider = $providerId !== ''
            ? Provider::query()->findOrFail($providerId)
            : Provider::query()->where('code', 'esimaccess')->first() ?? Provider::query()->where('type', 'esim')->firstOrFail();

        return $providerController->packages($request, $provider, $manager);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function record(RecordAuditLog $audit, Request $request, string $action, string $id, array $meta): void
    {
        $audit($action, $request->user(), EsimPlan::class, $id, null, $meta, $request->ip(), $request->userAgent());
    }
}
