<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Promotion extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = [
        'name',
        'code',
        'type',
        'value_minor',
        'percentage',
        'starts_at',
        'ends_at',
        'usage_limit',
        'usage_count',
        'per_user_limit',
        'is_active',
        'rules',
    ];

    protected function casts(): array
    {
        return [
            'value_minor' => 'integer',
            'percentage' => 'decimal:2',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'usage_limit' => 'integer',
            'usage_count' => 'integer',
            'per_user_limit' => 'integer',
            'is_active' => 'boolean',
            'rules' => 'array',
        ];
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(CouponRedemption::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
