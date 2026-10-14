<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\WalletStatus;
use App\Models\Concerns\HasUuidPrimaryKey;
use Database\Factories\WalletFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Wallet extends Model
{
    /** @use HasFactory<WalletFactory> */
    use HasFactory;

    use HasUuidPrimaryKey;

    protected $fillable = [
        'currency',
        'available_balance_minor',
        'held_balance_minor',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'available_balance_minor' => 'integer',
            'held_balance_minor' => 'integer',
            'status' => WalletStatus::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(WalletAccount::class);
    }

    public function holds(): HasMany
    {
        return $this->hasMany(WalletHold::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', WalletStatus::Active);
    }
}
