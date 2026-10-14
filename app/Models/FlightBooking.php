<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BookingStatus;
use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class FlightBooking extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = [
        'trip_item_id',
        'order_item_id',
        'provider_id',
        'booking_reference',
        'provider_reference',
        'airline_code',
        'status',
        'total_amount_minor',
        'currency',
        'passenger_count',
        'booked_at',
        'cancelled_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'status' => BookingStatus::class,
            'total_amount_minor' => 'integer',
            'passenger_count' => 'integer',
            'booked_at' => 'datetime',
            'cancelled_at' => 'datetime',
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

    public function segments(): HasMany
    {
        return $this->hasMany(FlightSegment::class);
    }

    public function passengers(): HasMany
    {
        return $this->hasMany(FlightPassenger::class);
    }
}
