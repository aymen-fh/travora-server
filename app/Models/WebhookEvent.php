<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\WebhookEventStatus;
use App\Integrations\Esim\Support\SecretMasker;
use App\Models\Concerns\HasCreatedAtOnly;
use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class WebhookEvent extends Model
{
    use HasCreatedAtOnly;
    use HasUuidPrimaryKey;

    protected $fillable = [
        'provider_id',
        'event_id',
        'event_type',
        'signature',
        'payload',
        'payload_hash',
        'signature_valid',
        'status',
        'attempt_count',
        'last_error',
        'received_at',
        'processed_at',
    ];

    protected $hidden = [
        'signature',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'signature_valid' => 'boolean',
            'status' => WebhookEventStatus::class,
            'attempt_count' => 'integer',
            'received_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', WebhookEventStatus::Pending);
    }

    /**
     * @return array<string, mixed>
     */
    public function maskedPayload(): array
    {
        return SecretMasker::mask($this->payload ?? []);
    }
}
