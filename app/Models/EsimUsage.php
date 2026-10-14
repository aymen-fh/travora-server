<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasCreatedAtOnly;
use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class EsimUsage extends Model
{
    use HasCreatedAtOnly;
    use HasUuidPrimaryKey;

    protected $table = 'esim_usage';

    protected $fillable = [
        'esim_profile_id',
        'recorded_at',
        'used_mb',
        'remaining_mb',
        'source',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'recorded_at' => 'datetime',
            'used_mb' => 'integer',
            'remaining_mb' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function esimProfile(): BelongsTo
    {
        return $this->belongsTo(EsimProfile::class);
    }
}
