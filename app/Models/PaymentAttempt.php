<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentStatus;
use App\Models\Concerns\HasCreatedAtOnly;
use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PaymentAttempt extends Model
{
    use HasCreatedAtOnly;
    use HasUuidPrimaryKey;

    protected $fillable = [
        'payment_id',
        'attempt_number',
        'status',
        'provider_reference',
        'response_code',
        'response_message',
    ];

    protected $hidden = [
        'request_payload',
        'response_payload',
    ];

    protected function casts(): array
    {
        return [
            'attempt_number' => 'integer',
            'status' => PaymentStatus::class,
            'request_payload' => 'array',
            'response_payload' => 'array',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
