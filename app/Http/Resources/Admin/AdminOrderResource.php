<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Order
 */
final class AdminOrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'user_id' => $this->user_id,
            'status' => $this->status?->value,
            'payment_status' => $this->payment_status?->value,
            'fulfillment_status' => $this->fulfillment_status?->value,
            'reconciliation_status' => $this->metadata['reconciliation_status'] ?? null,
            'metadata' => $this->metadata,
            'currency' => $this->currency,
            'total_minor' => $this->total_minor,
            'paid_minor' => $this->paid_minor,
            'placed_at' => $this->placed_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user?->id,
                'email' => $this->user?->email,
            ]),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item): array => [
                'id' => $item->id,
                'item_type' => $item->item_type?->value,
                'reference_type' => $item->reference_type,
                'reference_id' => $item->reference_id,
                'provider_id' => $item->provider_id,
                'name_snapshot' => $item->name_snapshot,
                'description_snapshot' => $item->description_snapshot,
                'quantity' => $item->quantity,
                'unit_price_minor' => $item->unit_price_minor,
                'discount_minor' => $item->discount_minor,
                'tax_minor' => $item->tax_minor,
                'total_minor' => $item->total_minor,
                'esim_profile_id' => $item->relationLoaded('esimProfile') ? $item->esimProfile?->id : null,
            ])->values()->all()),
        ];
    }
}
