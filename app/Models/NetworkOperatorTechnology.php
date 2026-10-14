<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasCompositePrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class NetworkOperatorTechnology extends Model
{
    use HasCompositePrimaryKey;

    public $timestamps = false;

    protected $primaryKey = ['network_operator_id', 'technology'];

    protected $fillable = [
        'network_operator_id',
        'technology',
    ];

    public function networkOperator(): BelongsTo
    {
        return $this->belongsTo(NetworkOperator::class);
    }
}
