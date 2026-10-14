<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RecordStatus;
use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class ProviderProduct extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = [
        'provider_id',
        'product_type',
        'external_product_id',
        'name',
        'status',
        'raw_data',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => RecordStatus::class,
            'raw_data' => 'array',
            'synced_at' => 'datetime',
        ];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function esimPlans(): HasMany
    {
        return $this->hasMany(EsimPlan::class);
    }

    public function simProducts(): HasMany
    {
        return $this->hasMany(SimProduct::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', RecordStatus::Active);
    }
}
