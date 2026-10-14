<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Order
 */
final class OrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'status' => $this->status?->value,
            'payment_status' => $this->payment_status?->value,
            'fulfillment_status' => $this->fulfillment_status?->value,
            'currency' => $this->currency,
            'total_minor' => $this->total_minor,
            'paid_minor' => $this->paid_minor,
            'placed_at' => $this->placed_at?->toIso8601String(),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item): array => [
                'id' => $item->id,
                'name' => $item->name_snapshot,
                'quantity' => $item->quantity,
                'unit_price_minor' => $item->unit_price_minor,
                'total_minor' => $item->total_minor,
                'plan_id' => $item->metadata['plan_id'] ?? $item->reference_id,
                'esim_profile_id' => $item->esimProfile?->id,
            ])->values()->all()),
        ];
    }
}
