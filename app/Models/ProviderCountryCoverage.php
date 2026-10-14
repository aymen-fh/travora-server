<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RecordStatus;
use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ProviderCountryCoverage extends Model
{
    use HasUuidPrimaryKey;

    protected $table = 'provider_country_coverage';

    protected $fillable = [
        'provider_id',
        'country_id',
        'service_type',
        'status',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'status' => RecordStatus::class,
            'metadata' => 'array',
        ];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }
}
