<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Models\EsimPlan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin EsimPlan
 */
final class AdminEsimPlanResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'provider_id' => $this->provider_id,
            'provider_name' => $this->whenLoaded('provider', fn () => $this->provider?->name),
            'provider_product_id' => $this->provider_product_id,
            'provider_product' => $this->whenLoaded('providerProduct', function (): ?array {
                $product = $this->providerProduct;
                if ($product === null) {
                    return null;
                }

                $raw = is_array($product->raw_data) ? $product->raw_data : [];

                return [
                    'id' => $product->id,
                    'provider_id' => $product->provider_id,
                    'external_product_id' => $product->external_product_id,
                    'status' => $product->status?->value,
                    'cost_minor' => isset($raw['cost_minor']) && is_numeric($raw['cost_minor']) ? (int) $raw['cost_minor'] : null,
                    'currency' => isset($raw['currency']) && is_string($raw['currency']) && $raw['currency'] !== '' ? $raw['currency'] : null,
                    'synced_at' => $product->synced_at?->toIso8601String(),
                ];
            }),
            'name' => $this->name,
            'description' => $this->description,
            'data_mb' => $this->data_mb,
            'is_unlimited' => $this->is_unlimited,
            'validity_days' => $this->validity_days,
            'hotspot_supported' => $this->hotspot_supported,
            'auto_topup_supported' => $this->auto_topup_supported,
            'is_featured' => $this->is_featured,
            'status' => $this->status?->value,
            'prices' => $this->whenLoaded('prices', fn () => $this->prices->map(fn ($price): array => [
                'id' => $price->id,
                'currency' => $price->currency,
                'price_minor' => $price->price_minor,
                'is_active' => $price->is_active,
                'starts_at' => $price->starts_at?->toIso8601String(),
                'ends_at' => $price->ends_at?->toIso8601String(),
            ])->values()->all()),
            'countries' => $this->whenLoaded('countries', fn () => $this->countries->map(fn ($country): array => [
                'id' => $country->id,
                'iso2' => $country->iso2,
                'name' => $country->name,
            ])->values()->all()),
            'networks' => $this->whenLoaded('networks', fn () => $this->networks->map(fn ($network): array => [
                'id' => $network->id,
                'name' => $network->name,
            ])->values()->all()),
        ];
    }
}
