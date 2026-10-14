<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OrderItemType;
use App\Models\Concerns\HasCreatedAtOnly;
use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class OrderItem extends Model
{
    use HasCreatedAtOnly;
    use HasUuidPrimaryKey;

    protected $fillable = [
        'order_id',
        'item_type',
        'reference_type',
        'reference_id',
        'provider_id',
        'name_snapshot',
        'description_snapshot',
        'quantity',
        'unit_price_minor',
        'discount_minor',
        'tax_minor',
        'total_minor',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'item_type' => OrderItemType::class,
            'quantity' => 'integer',
            'unit_price_minor' => 'integer',
            'discount_minor' => 'integer',
            'tax_minor' => 'integer',
            'total_minor' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function esimProfile(): HasOne
    {
        return $this->hasOne(EsimProfile::class);
    }

    public function simInventory(): HasMany
    {
        return $this->hasMany(SimInventory::class);
    }
}
