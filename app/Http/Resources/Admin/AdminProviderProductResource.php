<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Models\ProviderProduct;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ProviderProduct
 */
final class AdminProviderProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'provider_id' => $this->provider_id,
            'product_type' => $this->product_type,
            'external_product_id' => $this->external_product_id,
            'name' => $this->name,
            'status' => $this->status?->value,
            'synced_at' => $this->synced_at?->toIso8601String(),
        ];
    }
}
