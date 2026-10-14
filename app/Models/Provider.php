<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\HealthStatus;
use App\Enums\ProviderType;
use App\Enums\RecordStatus;
use App\Models\Concerns\HasUuidPrimaryKey;
use Database\Factories\ProviderFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Provider extends Model
{
    /** @use HasFactory<ProviderFactory> */
    use HasFactory;

    use HasUuidPrimaryKey;

    protected $fillable = [
        'type',
        'name',
        'code',
        'status',
        'priority',
        'health_status',
        'health_checked_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'type' => ProviderType::class,
            'status' => RecordStatus::class,
            'priority' => 'integer',
            'health_status' => HealthStatus::class,
            'health_checked_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function credentials(): HasMany
    {
        return $this->hasMany(ProviderCredential::class);
    }

    public function services(): HasMany
    {
        return $this->hasMany(ProviderService::class);
    }

    public function countryCoverage(): HasMany
    {
        return $this->hasMany(ProviderCountryCoverage::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(ProviderProduct::class);
    }

    public function externalReferences(): HasMany
    {
        return $this->hasMany(ProviderExternalReference::class);
    }

    public function healthChecks(): HasMany
    {
        return $this->hasMany(ProviderHealthCheck::class);
    }

    public function apiLogs(): HasMany
    {
        return $this->hasMany(ProviderApiLog::class);
    }

    public function esimPlans(): HasMany
    {
        return $this->hasMany(EsimPlan::class);
    }

    public function webhookEvents(): HasMany
    {
        return $this->hasMany(WebhookEvent::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', RecordStatus::Active);
    }
}
