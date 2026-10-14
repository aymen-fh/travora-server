<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Domain\Catalog\PriceWindow;
use App\Domain\MoneyMinor;
use App\Exceptions\CatalogUnavailableException;
use App\Models\EsimPlan;
use App\Models\EsimPlanPrice;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class SetEsimPlanPrice
{
    public function __invoke(
        EsimPlan $plan,
        MoneyMinor $amount,
        ?DateTimeInterface $startsAt = null,
        ?DateTimeInterface $endsAt = null,
        bool $active = true,
    ): EsimPlanPrice {
        try {
            new PriceWindow($startsAt, $endsAt);
        } catch (InvalidArgumentException $exception) {
            throw new CatalogUnavailableException($exception->getMessage());
        }

        if (strlen($amount->currency) !== 3) {
            throw new CatalogUnavailableException('Currency must be a 3-letter code.');
        }

        return DB::transaction(function () use ($plan, $amount, $startsAt, $endsAt, $active): EsimPlanPrice {
            if ($active) {
                EsimPlanPrice::query()
                    ->where('esim_plan_id', $plan->id)
                    ->where('currency', $amount->currency)
                    ->where('is_active', true)
                    ->update(['is_active' => false]);
            }

            $price = new EsimPlanPrice;
            $price->esim_plan_id = $plan->id;
            $price->currency = $amount->currency;
            $price->price_minor = $amount->amount;
            $price->starts_at = $startsAt;
            $price->ends_at = $endsAt;
            $price->is_active = $active;
            $price->save();

            return $price;
        });
    }
}
