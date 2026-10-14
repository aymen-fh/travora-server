<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Models\EsimPlanPrice;
use Illuminate\Support\Facades\DB;

final class DeactivateEsimPlanPrice
{
    public function __invoke(EsimPlanPrice $price): EsimPlanPrice
    {
        return DB::transaction(function () use ($price): EsimPlanPrice {
            $price->is_active = false;
            $price->save();

            return $price;
        });
    }
}
