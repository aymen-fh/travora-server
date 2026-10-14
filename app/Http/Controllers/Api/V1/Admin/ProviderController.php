<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Audit\RecordAuditLog;
use App\Actions\Providers\CreateProvider;
use App\Actions\Providers\DisableProvider;
use App\Actions\Providers\EnableProvider;
use App\Actions\Providers\QueryProviderBalance;
use App\Actions\Providers\QueryProviderDiscountRule;
use App\Actions\Providers\QueryProviderWebhook;
use App\Actions\Providers\SaveProviderWebhook;
use App\Actions\Providers\StoreProviderCredential;
use App\Actions\Providers\SyncProviderCountryCoverage;
use App\Actions\Providers\SyncProviderServices;
use App\Actions\Providers\UpdateProvider;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DisableProviderRequest;
use App\Http\Requests\Admin\QueryDiscountRuleRequest;
use App\Http\Requests\Admin\SaveProviderWebhookRequest;
use App\Http\Requests\Admin\StoreProviderCredentialRequest;
use App\Http\Requests\Admin\StoreProviderRequest;
use App\Http\Requests\Admin\SyncProviderCoverageRequest;
use App\Http\Requests\Admin\SyncProviderServicesRequest;
use App\Http\Requests\Admin\UpdateProviderRequest;
use App\Http\Resources\Admin\AdminProviderCoverageResource;
use App\Http\Resources\Admin\AdminProviderProductResource;
use App\Http\Resources\Admin\AdminProviderServiceResource;
use App\Http\Resources\Admin\MaskedProviderCredentialResource;
use App\Http\Resources\Admin\ProviderResource;
use App\Http\Responses\ApiResponse;
use App\Jobs\HealthCheckProviderJob;
use App\Jobs\SyncProviderProductsJob;
use App\Integrations\Esim\Contracts\PartnerEsimOperations;
use App\Integrations\Esim\Manager\ProviderManager;
use App\Models\Provider;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

final class ProviderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Provider::class);

        $page = Provider::query()
            ->withCount('credentials')
            ->when($request->string('search')->toString(), function ($query, string $search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner->where('name', 'like', '%'.$search.'%')
                        ->orWhere('code', 'like', '%'.$search.'%');
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->orderBy('priority')
            ->paginate($request->integer('per_page', 20));

        return ApiResponse::success(
            ProviderResource::collection($page->getCollection())->resolve(),
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

    public function store(StoreProviderRequest $request, CreateProvider $create): JsonResponse
    {
        $this->authorize('create', Provider::class);

        $provider = $create($request->user(), $request->validated(), $request);

        return ApiResponse::created(new ProviderResource($provider));
    }

    public function show(Provider $provider): JsonResponse
    {
        $this->authorize('view', $provider);

        return ApiResponse::success(new ProviderResource($provider->loadCount('credentials')));
    }

    public function update(UpdateProviderRequest $request, Provider $provider, UpdateProvider $update): JsonResponse
    {
        $this->authorize('update', $provider);

        return ApiResponse::success(new ProviderResource(($update)($request->user(), $provider, $request->validated(), $request)));
    }

    public function enable(Request $request, Provider $provider, EnableProvider $enable): JsonResponse
    {
        $this->authorize('enable', $provider);

        return ApiResponse::success(new ProviderResource(($enable)($request->user(), $provider, $request)), 'Enabled');
    }

    public function disable(DisableProviderRequest $request, Provider $provider, DisableProvider $disable): JsonResponse
    {
        $this->authorize('disable', $provider);

        return ApiResponse::success(
            new ProviderResource(($disable)($request->user(), $provider, $request->string('reason')->toString(), $request)),
            'Disabled',
        );
    }

    public function sync(Request $request, Provider $provider, RecordAuditLog $audit): JsonResponse
    {
        $this->authorize('syncProducts', $provider);

        Cache::forget("provider_raw_packages_{$provider->id}");

        if ($request->boolean('sync_now', false)) {
            $stats = app(\App\Actions\Providers\SyncProviderProducts::class)($provider);

            $audit(
                'provider.sync.completed',
                $request->user(),
                Provider::class,
                $provider->id,
                null,
                ['provider_id' => $provider->id, 'stats' => $stats],
                $request->ip(),
                $request->userAgent(),
            );

            return ApiResponse::success($stats, 'Sync completed');
        }

        SyncProviderProductsJob::dispatch($provider->id);

        $audit(
            'provider.sync.queued',
            $request->user(),
            Provider::class,
            $provider->id,
            null,
            ['provider_id' => $provider->id],
            $request->ip(),
            $request->userAgent(),
        );

        return ApiResponse::accepted(['provider_id' => $provider->id], 'Sync queued');
    }

    public function healthCheck(Request $request, Provider $provider, RecordAuditLog $audit): JsonResponse
    {
        $this->authorize('runHealthCheck', $provider);

        HealthCheckProviderJob::dispatch($provider->id);

        $audit(
            'provider.health_check.queued',
            $request->user(),
            Provider::class,
            $provider->id,
            null,
            ['provider_id' => $provider->id],
            $request->ip(),
            $request->userAgent(),
        );

        return ApiResponse::accepted(['provider_id' => $provider->id], 'Health check queued');
    }

    public function credentials(Provider $provider): JsonResponse
    {
        $this->authorize('viewMaskedCredentials', $provider);

        $credentials = $provider->credentials()->get();

        return ApiResponse::success(MaskedProviderCredentialResource::collection($credentials));
    }

    public function storeCredentials(
        StoreProviderCredentialRequest $request,
        Provider $provider,
        StoreProviderCredential $store,
    ): JsonResponse {
        $this->authorize('manageCredentials', $provider);

        $credential = $store(
            $request->user(),
            $provider,
            $request->string('environment')->toString(),
            $request->string('credential_key')->toString(),
            $request->string('secret_reference')->toString(),
            $request,
        );

        return ApiResponse::success(new MaskedProviderCredentialResource($credential), 'Stored');
    }

    public function services(Provider $provider): JsonResponse
    {
        $this->authorize('viewServices', $provider);

        return ApiResponse::success(
            AdminProviderServiceResource::collection($provider->services()->orderBy('priority')->get()),
        );
    }

    public function syncServices(
        SyncProviderServicesRequest $request,
        Provider $provider,
        SyncProviderServices $sync,
    ): JsonResponse {
        $this->authorize('manageServices', $provider);

        $provider = $sync($request->user(), $provider, $request->validated('services'), $request);

        return ApiResponse::success(
            AdminProviderServiceResource::collection($provider->services()->orderBy('priority')->get()),
            'Services synced',
        );
    }

    public function products(Request $request, Provider $provider): JsonResponse
    {
        $this->authorize('viewProducts', $provider);

        $page = $provider->products()
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('product_type'), fn ($query) => $query->where('product_type', $request->string('product_type')))
            ->latest('synced_at')
            ->paginate($request->integer('per_page', 20));

        return ApiResponse::success(
            AdminProviderProductResource::collection($page->getCollection())->resolve(),
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

    public function countries(Provider $provider): JsonResponse
    {
        $this->authorize('viewCoverage', $provider);

        return ApiResponse::success(
            AdminProviderCoverageResource::collection(
                $provider->countryCoverage()->with('country:id,name,name_ar,iso2')->orderBy('service_type')->get(),
            ),
        );
    }

    public function syncCountries(
        SyncProviderCoverageRequest $request,
        Provider $provider,
        SyncProviderCountryCoverage $sync,
    ): JsonResponse {
        $this->authorize('manageCoverage', $provider);

        $provider = $sync($request->user(), $provider, $request->validated('coverage'), $request);

        return ApiResponse::success(
            AdminProviderCoverageResource::collection(
                $provider->countryCoverage()->with('country:id,name,name_ar,iso2')->orderBy('service_type')->get(),
            ),
            'Coverage synced',
        );
    }

    public function balance(Provider $provider, QueryProviderBalance $query): JsonResponse
    {
        $this->authorize('view', $provider);

        return ApiResponse::success($query($provider));
    }

    public function discountRules(QueryDiscountRuleRequest $request, Provider $provider, QueryProviderDiscountRule $query): JsonResponse
    {
        $this->authorize('view', $provider);

        return ApiResponse::success($query($provider, $request->string('discount_rule_code')->toString()));
    }

    public function showWebhook(Provider $provider, QueryProviderWebhook $query): JsonResponse
    {
        $this->authorize('view', $provider);

        return ApiResponse::success($query($provider));
    }

    public function saveWebhook(SaveProviderWebhookRequest $request, Provider $provider, SaveProviderWebhook $save): JsonResponse
    {
        $this->authorize('manageWebhook', $provider);

        $actor = $request->user();
        if (! $actor instanceof User) {
            abort(401);
        }

        $webhook = $request->string('webhook')->toString();
        $save($actor, $provider, $webhook, $request->ip(), $request->userAgent());

        return ApiResponse::success(['webhook' => $webhook], 'Saved');
    }

    public function packages(Request $request, Provider $provider, ProviderManager $manager): JsonResponse
    {
        $this->authorize('view', $provider);

        $driver = $manager->driver($provider);
        $search = strtolower(trim((string) $request->input('search', '')));
        $locationCode = strtoupper(trim((string) $request->input('location_code', '')));
        $type = strtoupper(trim((string) $request->input('type', '')));
        $page = max(1, (int) $request->input('page', 1));
        $perPage = min(100, max(1, (int) $request->input('per_page', 20)));
        $forceRefresh = $request->boolean('refresh', false);

        $cacheKey = "provider_raw_packages_{$provider->id}";
        if ($forceRefresh) {
            Cache::forget($cacheKey);
        }

        /** @var array<int, array<string, mixed>> $allPackages */
        $allPackages = Cache::remember($cacheKey, 300, function () use ($driver) {
            if ($driver instanceof PartnerEsimOperations) {
                return $driver->listRawPackages();
            }

            return [];
        });

        $filtered = array_filter($allPackages, function ($pkg) use ($search, $locationCode, $type) {
            if ($locationCode !== '') {
                $pkgLoc = strtoupper((string) ($pkg['locationCode'] ?? $pkg['location'] ?? ''));
                if (! str_contains($pkgLoc, $locationCode)) {
                    return false;
                }
            }

            if ($type !== '') {
                $pkgType = strtoupper((string) ($pkg['type'] ?? ''));
                if ($pkgType !== '' && $pkgType !== $type) {
                    return false;
                }
            }

            if ($search !== '') {
                $name = strtolower((string) ($pkg['name'] ?? ''));
                $code = strtolower((string) ($pkg['packageCode'] ?? ''));
                $slug = strtolower((string) ($pkg['slug'] ?? ''));
                $loc = strtolower((string) ($pkg['location'] ?? $pkg['locationCode'] ?? ''));
                if (! str_contains($name, $search) && ! str_contains($code, $search) && ! str_contains($slug, $search) && ! str_contains($loc, $search)) {
                    return false;
                }
            }

            return true;
        });

        $total = count($filtered);
        $sliced = array_slice(array_values($filtered), ($page - 1) * $perPage, $perPage);

        $normalized = array_map(function ($pkg) {
            $priceRaw = (int) ($pkg['price'] ?? 0);
            $retailRaw = (int) ($pkg['retailPrice'] ?? 0);
            $volume = (int) ($pkg['volume'] ?? 0);
            $dataMb = intdiv(max(0, $volume), 1048576);
            $dataType = (int) ($pkg['dataType'] ?? 1);
            $networks = $pkg['locationNetworkList'] ?? [];
            $operators = [];
            if (is_array($networks)) {
                foreach ($networks as $net) {
                    if (is_array($net) && isset($net['operatorList']) && is_array($net['operatorList'])) {
                        foreach ($net['operatorList'] as $op) {
                            if (! empty($op['operatorName'])) {
                                $operators[] = $op['operatorName'].(! empty($op['networkType']) ? " ({$op['networkType']})" : '');
                            }
                        }
                    }
                }
            }

            return [
                'package_code' => (string) ($pkg['packageCode'] ?? ''),
                'slug' => (string) ($pkg['slug'] ?? ''),
                'name' => (string) ($pkg['name'] ?? ''),
                'cost_usd' => round($priceRaw / 10000, 2),
                'cost_minor' => (int) round(($priceRaw / 10000) * 100),
                'retail_usd' => round($retailRaw / 10000, 2),
                'retail_minor' => (int) round(($retailRaw / 10000) * 100),
                'currency' => (string) ($pkg['currencyCode'] ?? 'USD'),
                'data_mb' => $dataMb,
                'data_gb' => round($dataMb / 1024, 1),
                'is_unlimited' => $dataType === 4,
                'validity_days' => (int) ($pkg['duration'] ?? 0),
                'location_code' => (string) ($pkg['locationCode'] ?? $pkg['location'] ?? ''),
                'speed' => (string) ($pkg['speed'] ?? ''),
                'operators' => array_values(array_unique($operators)),
                'networks' => $networks,
                'description' => (string) ($pkg['description'] ?? ''),
            ];
        }, $sliced);

        return ApiResponse::success(
            $normalized,
            'OK',
            200,
            [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => (int) ceil($total / max(1, $perPage)),
            ]
        );
    }
}
