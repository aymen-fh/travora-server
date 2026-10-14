<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RecordStatus;
use App\Models\Concerns\HasUuidPrimaryKey;
use Database\Factories\EsimPlanFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class EsimPlan extends Model
{
    /** @use HasFactory<EsimPlanFactory> */
    use HasFactory;

    use HasUuidPrimaryKey;

    protected $fillable = [
        'provider_id',
        'provider_product_id',
        'name',
        'description',
        'data_mb',
        'is_unlimited',
        'validity_days',
        'speed_type',
        'hotspot_supported',
        'activation_policy',
        'fair_usage_policy',
        'auto_topup_supported',
        'is_featured',
        'status',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'data_mb' => 'integer',
            'is_unlimited' => 'boolean',
            'validity_days' => 'integer',
            'hotspot_supported' => 'boolean',
            'auto_topup_supported' => 'boolean',
            'is_featured' => 'boolean',
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

    public function countries(): BelongsToMany
    {
        return $this->belongsToMany(Country::class, 'esim_plan_countries');
    }

    public function networks(): BelongsToMany
    {
        return $this->belongsToMany(NetworkOperator::class, 'esim_plan_networks');
    }

    public function prices(): HasMany
    {
        return $this->hasMany(EsimPlanPrice::class);
    }

    public function profiles(): HasMany
    {
        return $this->hasMany(EsimProfile::class, 'plan_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', RecordStatus::Active);
    }

    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }
}
