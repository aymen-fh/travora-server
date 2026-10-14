<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EsimOperationStatus;
use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class EsimRenewal extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = [
        'esim_profile_id',
        'current_plan_id',
        'new_plan_id',
        'order_id',
        'order_item_id',
        'provider_id',
        'provider_reference',
        'idempotency_key',
        'status',
        'amount_minor',
        'currency',
        'effective_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => EsimOperationStatus::class,
            'amount_minor' => 'integer',
            'effective_at' => 'datetime',
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

    public function currentPlan(): BelongsTo
    {
        return $this->belongsTo(EsimPlan::class, 'current_plan_id');
    }

    public function newPlan(): BelongsTo
    {
        return $this->belongsTo(EsimPlan::class, 'new_plan_id');
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
