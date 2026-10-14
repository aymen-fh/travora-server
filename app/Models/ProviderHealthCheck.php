<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\HealthStatus;
use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ProviderHealthCheck extends Model
{
    use HasUuidPrimaryKey;

    public $timestamps = false;

    protected $fillable = [
        'provider_id',
        'service_type',
        'status',
        'latency_ms',
        'message',
        'checked_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'status' => HealthStatus::class,
            'latency_ms' => 'integer',
            'checked_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }
}
