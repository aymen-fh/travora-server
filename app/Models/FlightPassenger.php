<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class FlightPassenger extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = [
        'flight_booking_id',
        'first_name',
        'last_name',
        'passenger_type',
        'date_of_birth',
        'document_type',
        'nationality_country_id',
    ];

    protected $hidden = [
        'document_number_encrypted',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'document_number_encrypted' => 'encrypted',
        ];
    }

    public function flightBooking(): BelongsTo
    {
        return $this->belongsTo(FlightBooking::class);
    }

    public function nationalityCountry(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'nationality_country_id');
    }
}
