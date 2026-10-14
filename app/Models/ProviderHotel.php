<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ProviderHotel extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = [
        'provider_id',
        'hotel_id',
        'external_hotel_id',
        'external_name',
        'raw_data',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'raw_data' => 'array',
            'synced_at' => 'datetime',
        ];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }
}
