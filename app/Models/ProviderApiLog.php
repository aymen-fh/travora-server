<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasCreatedAtOnly;
use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ProviderApiLog extends Model
{
    use HasCreatedAtOnly;
    use HasUuidPrimaryKey;

    protected $fillable = [
        'provider_id',
        'service_type',
        'direction',
        'endpoint',
        'method',
        'status_code',
        'duration_ms',
        'correlation_id',
        'error_message',
    ];

    protected $hidden = [
        'request_body',
        'response_body',
    ];

    protected function casts(): array
    {
        return [
            'status_code' => 'integer',
            'duration_ms' => 'integer',
            'request_body' => 'array',
            'response_body' => 'array',
        ];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }
}
