<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TripItemType;
use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class TripItem extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = [
        'trip_id',
        'item_type',
        'reference_id',
        'title',
        'starts_at',
        'ends_at',
        'location_text',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'item_type' => TripItemType::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function flightBooking(): HasOne
    {
        return $this->hasOne(FlightBooking::class);
    }

    public function hotelBooking(): HasOne
    {
        return $this->hasOne(HotelBooking::class);
    }

    public function carBooking(): HasOne
    {
        return $this->hasOne(CarBooking::class);
    }

    public function insurancePolicy(): HasOne
    {
        return $this->hasOne(InsurancePolicy::class);
    }

    public function busBooking(): HasOne
    {
        return $this->hasOne(BusBooking::class);
    }
}
