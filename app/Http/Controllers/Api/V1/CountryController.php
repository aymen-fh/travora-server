<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CountryResource;
use App\Http\Responses\ApiResponse;
use App\Models\Country;
use Illuminate\Http\JsonResponse;

final class CountryController extends Controller
{
    public function index(): JsonResponse
    {
        $countries = Country::query()
            ->active()
            ->orderBy('name')
            ->get();

        return ApiResponse::success(CountryResource::collection($countries));
    }

    public function show(Country $country): JsonResponse
    {
        return ApiResponse::success(new CountryResource($country));
    }
}
