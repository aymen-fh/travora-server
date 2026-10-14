<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\WalletHoldStatus;
use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class WalletHold extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = [
        'wallet_id',
        'amount_minor',
        'currency',
        'status',
        'reference_type',
        'reference_id',
        'idempotency_key',
        'expires_at',
        'released_at',
        'captured_at',
    ];

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'status' => WalletHoldStatus::class,
            'expires_at' => 'datetime',
            'released_at' => 'datetime',
            'captured_at' => 'datetime',
        ];
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', WalletHoldStatus::Active);
    }
}
