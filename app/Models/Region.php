<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Database\Factories\RegionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Region extends Model
{
    /** @use HasFactory<RegionFactory> */
    use HasFactory;

    use HasUuidPrimaryKey;

    protected $fillable = [
        'name',
        'code',
    ];

    public function countries(): HasMany
    {
        return $this->hasMany(Country::class);
    }
}
