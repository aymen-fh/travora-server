<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasCreatedAtOnly;
use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class EsimActivationEvent extends Model
{
    use HasCreatedAtOnly;
    use HasUuidPrimaryKey;

    protected $fillable = [
        'esim_profile_id',
        'event_type',
        'status',
        'provider_reference',
        'message',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function esimProfile(): BelongsTo
    {
        return $this->belongsTo(EsimProfile::class);
    }
}
