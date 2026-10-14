<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ProviderCredential extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = [
        'provider_id',
        'environment',
        'credential_key',
        'secret_reference',
    ];

    protected $hidden = [
        'secret_reference',
    ];

    protected function casts(): array
    {
        return [
            'secret_reference' => 'encrypted',
        ];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }
}
