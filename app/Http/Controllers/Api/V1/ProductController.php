<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\ProductStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\CountryResource;
use App\Http\Resources\ProductResource;
use App\Http\Responses\ApiResponse;
use App\Models\Country;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Product::query()
            ->sellable()
            ->with(['coverages.country'])
            ->when($request->string('country')->toString(), fn ($q, $iso2) => $q->forCountry($iso2))
            ->when($request->boolean('featured'), fn ($q) => $q->where('is_featured', true))
            ->orderBy('price');

        $page = $query->paginate($request->integer('per_page', 20));

        return ApiResponse::success(
            ProductResource::collection($page->getCollection())->resolve(),
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

    public function show(Product $product): JsonResponse
    {
        abort_unless($product->status === ProductStatus::Active, 404);

        $product->load(['coverages.country']);

        return ApiResponse::success(new ProductResource($product));
    }

    public function destinations(Request $request): JsonResponse
    {
        $countries = Country::query()
            ->active()
            ->whereHas('coverages.product', fn ($q) => $q->sellable())
            ->orderBy('name_en')
            ->paginate($request->integer('per_page', 50));

        return ApiResponse::success(
            CountryResource::collection($countries->getCollection())->resolve(),
            'OK',
            200,
            [
                'current_page' => $countries->currentPage(),
                'per_page' => $countries->perPage(),
                'total' => $countries->total(),
                'last_page' => $countries->lastPage(),
            ],
        );
    }
}
