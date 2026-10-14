<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class EsimUsageDaily extends Model
{
    use HasUuidPrimaryKey;

    protected $table = 'esim_usage_daily';

    protected $fillable = [
        'esim_profile_id',
        'usage_date',
        'used_mb',
        'remaining_mb',
    ];

    protected function casts(): array
    {
        return [
            'usage_date' => 'date',
            'used_mb' => 'integer',
            'remaining_mb' => 'integer',
        ];
    }

    public function esimProfile(): BelongsTo
    {
        return $this->belongsTo(EsimProfile::class);
    }
}
