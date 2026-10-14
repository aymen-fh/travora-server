<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Database\Factories\NetworkOperatorFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class NetworkOperator extends Model
{
    /** @use HasFactory<NetworkOperatorFactory> */
    use HasFactory;

    use HasUuidPrimaryKey;

    protected $fillable = [
        'country_id',
        'name',
        'mcc',
        'mnc',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function technologies(): HasMany
    {
        return $this->hasMany(NetworkOperatorTechnology::class);
    }

    public function esimPlans(): BelongsToMany
    {
        return $this->belongsToMany(EsimPlan::class, 'esim_plan_networks');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
