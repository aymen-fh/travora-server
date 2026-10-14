<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class SimInventory extends Model
{
    use HasUuidPrimaryKey;

    protected $table = 'sim_inventory';

    protected $fillable = [
        'sim_product_id',
        'external_inventory_id',
        'iccid',
        'imsi',
        'msisdn',
        'status',
        'activated_at',
        'expires_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'activated_at' => 'datetime',
            'expires_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function simProduct(): BelongsTo
    {
        return $this->belongsTo(SimProduct::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }
}
