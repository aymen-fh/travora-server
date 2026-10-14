<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EsimOperationStatus;
use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class EsimTopup extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = [
        'esim_profile_id',
        'order_id',
        'order_item_id',
        'provider_id',
        'provider_reference',
        'idempotency_key',
        'data_mb',
        'is_unlimited',
        'amount_minor',
        'currency',
        'status',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'data_mb' => 'integer',
            'is_unlimited' => 'boolean',
            'amount_minor' => 'integer',
            'status' => EsimOperationStatus::class,
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function esimProfile(): BelongsTo
    {
        return $this->belongsTo(EsimProfile::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }
}
