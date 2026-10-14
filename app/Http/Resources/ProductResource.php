<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Product;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Product
 */
final class ProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $country = $this->coverages->first()?->country;
        $locale = $request->getPreferredLanguage(['en', 'ar']) ?? 'en';

        return [
            'id' => $this->uuid,
            'name' => $this->name,
            'description' => $this->description,
            'country' => $country === null ? null : [
                'code' => $country->iso2,
                'name' => $country->localizedName($locale),
                'flag' => $country->flag,
            ],
            'coverage' => [
                'type' => $this->coverage_type?->value,
                'description' => $this->coverage_description,
            ],
            'data' => [
                'amount' => $this->is_unlimited ? null : $this->data_amount,
                'unit' => $this->data_unit?->value,
                'unlimited' => $this->is_unlimited,
            ],
            'validity' => [
                'days' => $this->validity_days,
            ],
            'price' => Money::of((string) ($this->sale_price ?? $this->price), $this->currency)->toDisplayArray(),
            'is_featured' => $this->is_featured,
        ];
    }
}
