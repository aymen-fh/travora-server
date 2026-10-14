<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Esim\BuildEsimInstallationPayload;
use App\Actions\Esim\RefreshEsimUsage;
use App\Actions\Esim\RenewEsim;
use App\Actions\Esim\TopUpEsim;
use App\Http\Controllers\Controller;
use App\Http\Requests\Esim\StoreEsimRenewalRequest;
use App\Http\Requests\Esim\StoreEsimTopupRequest;
use App\Http\Resources\EsimResource;
use App\Http\Responses\ApiResponse;
use App\Models\EsimPlan;
use App\Models\EsimProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class EsimController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', EsimProfile::class);

        $page = EsimProfile::query()
            ->ownedBy($request->user()->id)
            ->latest()
            ->paginate($request->integer('per_page', 20));

        return ApiResponse::success(
            EsimResource::collection($page->getCollection())->resolve(),
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

    public function show(EsimProfile $profile): JsonResponse
    {
        $this->authorize('view', $profile);

        return ApiResponse::success(new EsimResource($profile));
    }

    public function usage(EsimProfile $profile): JsonResponse
    {
        $this->authorize('viewUsage', $profile);

        $snapshots = $profile->usageSnapshots()->latest('recorded_at')->limit(50)->get()->map(fn ($usage): array => [
            'id' => $usage->id,
            'used_mb' => $usage->used_mb,
            'remaining_mb' => $usage->remaining_mb,
            'source' => $usage->source,
            'recorded_at' => $usage->recorded_at?->toIso8601String(),
        ]);

        return ApiResponse::success([
            'data_total_mb' => $profile->data_total_mb,
            'data_remaining_mb' => $profile->data_remaining_mb,
            'snapshots' => $snapshots,
        ]);
    }

    public function refreshUsage(EsimProfile $profile, RefreshEsimUsage $refresh): JsonResponse
    {
        $this->authorize('viewUsage', $profile);

        $usage = $refresh($profile);

        return ApiResponse::success([
            'used_mb' => $usage->used_mb,
            'remaining_mb' => $usage->remaining_mb,
            'source' => $usage->source,
            'recorded_at' => $usage->recorded_at?->toIso8601String(),
        ]);
    }

    public function installation(Request $request, EsimProfile $profile, BuildEsimInstallationPayload $build): JsonResponse
    {
        $this->authorize('view', $profile);

        return ApiResponse::success(($build)($request->user(), $profile)->toArray());
    }

    public function topup(StoreEsimTopupRequest $request, EsimProfile $profile, TopUpEsim $topUp): JsonResponse
    {
        $this->authorize('view', $profile);

        $plan = EsimPlan::query()->findOrFail($request->string('plan_id')->toString());
        $key = $request->header('Idempotency-Key') ?: (string) Str::uuid();
        $topup = $topUp($request->user(), $profile, $plan, $key);

        return ApiResponse::created([
            'id' => $topup->id,
            'status' => $topup->status?->value,
            'amount_minor' => $topup->amount_minor,
            'currency' => $topup->currency,
        ]);
    }

    public function renew(StoreEsimRenewalRequest $request, EsimProfile $profile, RenewEsim $renew): JsonResponse
    {
        $this->authorize('view', $profile);

        $plan = EsimPlan::query()->findOrFail($request->string('plan_id')->toString());
        $key = $request->header('Idempotency-Key') ?: (string) Str::uuid();
        $renewal = $renew($request->user(), $profile, $plan, $key);

        return ApiResponse::created([
            'id' => $renewal->id,
            'status' => $renewal->status?->value,
            'amount_minor' => $renewal->amount_minor,
            'currency' => $renewal->currency,
        ]);
    }
}
