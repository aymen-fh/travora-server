<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Payment
 */
final class AdminPaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'payment_number' => $this->payment_number,
            'user_id' => $this->user_id,
            'order_id' => $this->order_id,
            'provider_id' => $this->provider_id,
            'type' => $this->type?->value,
            'method_type' => $this->whenLoaded('paymentMethod', fn () => $this->paymentMethod?->type),
            'status' => $this->status?->value,
            'amount_minor' => $this->amount_minor,
            'refunded_minor' => $this->refunded_minor,
            'currency' => $this->currency,
            'provider_reference' => $this->provider_reference,
            'authorized_at' => $this->authorized_at?->toIso8601String(),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'failed_at' => $this->failed_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user?->id,
                'email' => $this->user?->email,
            ]),
            'order' => $this->whenLoaded('order', fn () => [
                'id' => $this->order?->id,
                'order_number' => $this->order?->order_number,
            ]),
        ];
    }
}
