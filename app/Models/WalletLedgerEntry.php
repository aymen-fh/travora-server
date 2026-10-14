<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasCreatedAtOnly;
use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class WalletLedgerEntry extends Model
{
    use HasCreatedAtOnly;
    use HasUuidPrimaryKey;

    protected $fillable = [
        'wallet_transaction_id',
        'wallet_account_id',
        'debit_minor',
        'credit_minor',
    ];

    protected function casts(): array
    {
        return [
            'debit_minor' => 'integer',
            'credit_minor' => 'integer',
        ];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(WalletTransaction::class, 'wallet_transaction_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(WalletAccount::class, 'wallet_account_id');
    }
}
