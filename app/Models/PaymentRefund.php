<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RefundStatus;
use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PaymentRefund extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = [
        'payment_id',
        'provider_id',
        'amount_minor',
        'currency',
        'status',
        'provider_reference',
        'idempotency_key',
        'reason',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'status' => RefundStatus::class,
            'metadata' => 'array',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }
}
