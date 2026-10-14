<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RecordStatus;
use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class SimProduct extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = [
        'provider_id',
        'provider_product_id',
        'country_id',
        'type',
        'name',
        'description',
        'data_mb',
        'is_unlimited',
        'validity_days',
        'price_minor',
        'currency',
        'status',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'data_mb' => 'integer',
            'is_unlimited' => 'boolean',
            'validity_days' => 'integer',
            'price_minor' => 'integer',
            'status' => RecordStatus::class,
            'metadata' => 'array',
        ];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function providerProduct(): BelongsTo
    {
        return $this->belongsTo(ProviderProduct::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function inventory(): HasMany
    {
        return $this->hasMany(SimInventory::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', RecordStatus::Active);
    }
}
