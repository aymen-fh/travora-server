<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BookingStatus;
use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class HotelBooking extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = [
        'trip_item_id',
        'order_item_id',
        'hotel_id',
        'provider_id',
        'booking_reference',
        'provider_reference',
        'status',
        'guest_name_snapshot',
        'room_name_snapshot',
        'room_count',
        'guest_count',
        'check_in_at',
        'check_out_at',
        'total_amount_minor',
        'currency',
        'cancellation_policy',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'status' => BookingStatus::class,
            'room_count' => 'integer',
            'guest_count' => 'integer',
            'check_in_at' => 'datetime',
            'check_out_at' => 'datetime',
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

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }
}
