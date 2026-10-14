<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasCreatedAtOnly;
use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class OrderStatusHistory extends Model
{
    use HasCreatedAtOnly;
    use HasUuidPrimaryKey;

    protected $table = 'order_status_history';

    protected $fillable = [
        'order_id',
        'state_type',
        'from_value',
        'to_value',
        'reason',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
