<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Airport extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = [
        'country_id',
        'city_id',
        'name',
        'iata_code',
        'icao_code',
        'latitude',
        'longitude',
        'timezone',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:6',
            'longitude' => 'decimal:6',
            'is_active' => 'boolean',
        ];
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function departingSegments(): HasMany
    {
        return $this->hasMany(FlightSegment::class, 'origin_airport_id');
    }

    public function arrivingSegments(): HasMany
    {
        return $this->hasMany(FlightSegment::class, 'destination_airport_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
