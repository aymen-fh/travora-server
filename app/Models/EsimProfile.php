<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EsimProfileStatus;
use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class EsimProfile extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = [
        'order_item_id',
        'plan_id',
        'provider_id',
        'country_id',
        'external_profile_id',
        'iccid',
        'matching_id',
        'smdp_address',
        'status',
        'data_total_mb',
        'data_remaining_mb',
        'valid_from',
        'expires_at',
        'activated_at',
        'installed_at',
        'last_synced_at',
        'metadata',
    ];

    protected $hidden = [
        'activation_code_encrypted',
        'confirmation_code_encrypted',
    ];

    protected function casts(): array
    {
        return [
            'status' => EsimProfileStatus::class,
            'activation_code_encrypted' => 'encrypted',
            'confirmation_code_encrypted' => 'encrypted',
            'data_total_mb' => 'integer',
            'data_remaining_mb' => 'integer',
            'valid_from' => 'datetime',
            'expires_at' => 'datetime',
            'activated_at' => 'datetime',
            'installed_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(EsimPlan::class, 'plan_id');
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function installations(): HasMany
    {
        return $this->hasMany(EsimInstallation::class);
    }

    public function activationEvents(): HasMany
    {
        return $this->hasMany(EsimActivationEvent::class);
    }

    public function usageSnapshots(): HasMany
    {
        return $this->hasMany(EsimUsage::class);
    }

    public function dailyUsage(): HasMany
    {
        return $this->hasMany(EsimUsageDaily::class);
    }

    public function topups(): HasMany
    {
        return $this->hasMany(EsimTopup::class);
    }

    public function renewals(): HasMany
    {
        return $this->hasMany(EsimRenewal::class);
    }

    public function scopeOwnedBy(Builder $query, string $userId): Builder
    {
        return $query->where('user_id', $userId);
    }
}
