<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Models\PaymentAttempt;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PaymentAttempt
 */
final class AdminPaymentAttemptResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'payment_id' => $this->payment_id,
            'attempt_number' => $this->attempt_number,
            'status' => $this->status?->value,
            'provider_reference' => $this->provider_reference,
            'response_code' => $this->response_code,
            'response_message' => $this->response_message,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
