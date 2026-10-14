<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BookingStatus;
use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class InsurancePolicy extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = [
        'trip_item_id',
        'order_item_id',
        'provider_id',
        'policy_number',
        'provider_reference',
        'status',
        'coverage_type',
        'destination_country_id',
        'starts_at',
        'ends_at',
        'insured_name_snapshot',
        'total_amount_minor',
        'currency',
        'policy_document_url',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'status' => BookingStatus::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
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

    public function destinationCountry(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'destination_country_id');
    }
}
