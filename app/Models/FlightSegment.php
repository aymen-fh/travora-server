<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasCreatedAtOnly;
use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class FlightSegment extends Model
{
    use HasCreatedAtOnly;
    use HasUuidPrimaryKey;

    protected $fillable = [
        'flight_booking_id',
        'origin_airport_id',
        'destination_airport_id',
        'airline_code',
        'flight_number',
        'cabin_class',
        'departure_at',
        'arrival_at',
        'sequence',
    ];

    protected function casts(): array
    {
        return [
            'departure_at' => 'datetime',
            'arrival_at' => 'datetime',
            'sequence' => 'integer',
        ];
    }

    public function flightBooking(): BelongsTo
    {
        return $this->belongsTo(FlightBooking::class);
    }

    public function originAirport(): BelongsTo
    {
        return $this->belongsTo(Airport::class, 'origin_airport_id');
    }

    public function destinationAirport(): BelongsTo
    {
        return $this->belongsTo(Airport::class, 'destination_airport_id');
    }
}
