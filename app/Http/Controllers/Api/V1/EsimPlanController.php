<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\SearchPublicEsimPlansRequest;
use App\Http\Resources\PublicEsimPlanResource;
use App\Http\Responses\ApiResponse;
use App\Models\EsimPlan;
use App\Queries\Catalog\SearchPublicEsimPlans;
use App\Queries\Catalog\ShowPublicEsimPlan;
use Illuminate\Http\JsonResponse;

final class EsimPlanController extends Controller
{
    public function index(SearchPublicEsimPlansRequest $request, SearchPublicEsimPlans $search): JsonResponse
    {
        $filters = collect($request->validated())
            ->reject(fn (mixed $value): bool => $value === null || $value === '')
            ->all();

        return ApiResponse::success(
            PublicEsimPlanResource::collection($search($filters)),
        );
    }

    public function show(EsimPlan $esimPlan, SearchPublicEsimPlansRequest $request, ShowPublicEsimPlan $show): JsonResponse
    {
        $currency = strtoupper((string) ($request->validated('currency') ?: 'LYD'));

        return ApiResponse::success(new PublicEsimPlanResource($show($esimPlan, $currency)));
    }
}
