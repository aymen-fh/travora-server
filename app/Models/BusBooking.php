<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BookingStatus;
use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class BusBooking extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = [
        'trip_item_id',
        'order_item_id',
        'provider_id',
        'booking_reference',
        'provider_reference',
        'status',
        'origin_city_id',
        'destination_city_id',
        'carrier_name',
        'seat_number',
        'departure_at',
        'arrival_at',
        'passenger_name_snapshot',
        'total_amount_minor',
        'currency',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'status' => BookingStatus::class,
            'departure_at' => 'datetime',
            'arrival_at' => 'datetime',
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

    public function originCity(): BelongsTo
    {
        return $this->belongsTo(City::class, 'origin_city_id');
    }

    public function destinationCity(): BelongsTo
    {
        return $this->belongsTo(City::class, 'destination_city_id');
    }
}
