<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasCompositePrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class EsimPlanCountry extends Model
{
    use HasCompositePrimaryKey;

    public $timestamps = false;

    protected $primaryKey = ['esim_plan_id', 'country_id'];

    protected $fillable = [
        'esim_plan_id',
        'country_id',
    ];

    public function esimPlan(): BelongsTo
    {
        return $this->belongsTo(EsimPlan::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }
}
