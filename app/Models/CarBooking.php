<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BookingStatus;
use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CarBooking extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = [
        'trip_item_id',
        'order_item_id',
        'provider_id',
        'booking_reference',
        'provider_reference',
        'status',
        'pickup_city_id',
        'dropoff_city_id',
        'pickup_location',
        'dropoff_location',
        'pickup_at',
        'dropoff_at',
        'vehicle_class',
        'driver_age',
        'total_amount_minor',
        'currency',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'status' => BookingStatus::class,
            'pickup_at' => 'datetime',
            'dropoff_at' => 'datetime',
            'driver_age' => 'integer',
            'total_amount_minor' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tripItem(): BelongsTo
    {
        return $this->belongsTo(TripItem::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function pickupCity(): BelongsTo
    {
        return $this->belongsTo(City::class, 'pickup_city_id');
    }

    public function dropoffCity(): BelongsTo
    {
        return $this->belongsTo(City::class, 'dropoff_city_id');
    }
}
