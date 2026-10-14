<?php

declare(strict_types=1);

namespace App\Services\Pricing;

use App\Models\PricingRule;
use App\Models\Product;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Carbon;

final class PricingEngine
{
    /**
     * @return array{
     *     cost: string,
     *     price: string,
     *     discount: string,
     *     tax: string,
     *     total: string,
     *     profit: string,
     *     currency: string
     * }
     */
    public function quote(Product $product, ?User $user = null, int $quantity = 1): array
    {
        $cost = Money::of((string) $product->cost_price, $product->currency)
            ->multiply((string) $quantity);

        $base = Money::of((string) ($product->sale_price ?? $product->price), $product->currency)
            ->multiply((string) $quantity);

        $priced = $this->applyRules($product, $user, $base);
        $discount = $base->subtract($priced);
        if ($discount->isNegative()) {
            $discount = Money::zero($product->currency);
            $priced = $base;
        }

        $tax = Money::zero($product->currency);
        $total = $priced->add($tax);
        $profit = $total->subtract($cost);

        return [
            'cost' => $cost->amount,
            'price' => $base->amount,
            'discount' => $discount->amount,
            'tax' => $tax->amount,
            'total' => $total->amount,
            'profit' => $profit->amount,
            'currency' => $product->currency,
        ];
    }

    private function applyRules(Product $product, ?User $user, Money $base): Money
    {
        $now = Carbon::now();

        $rules = PricingRule::query()
            ->where('is_active', true)
            ->where(function ($query) use ($now): void {
                $query->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(function ($query) use ($now): void {
                $query->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
            })
            ->orderBy('priority')
            ->get();

        $price = $base;

        foreach ($rules as $rule) {
            if ($rule->product_id !== null && $rule->product_id !== $product->id) {
                continue;
            }
            if ($rule->provider_id !== null && $rule->provider_id !== $product->provider_id) {
                continue;
            }
            if ($rule->user_id !== null && ($user === null || $rule->user_id !== $user->id)) {
                continue;
            }

            $price = match ($rule->type) {
                'markup_percent' => $price->add($price->percentage((string) $rule->value)),
                'markup_fixed' => $price->add(Money::of((string) $rule->value, $price->currency)),
                'discount_percent' => $price->subtract($price->percentage((string) $rule->value)),
                'discount_fixed' => $price->subtract(Money::of((string) $rule->value, $price->currency)),
                default => $price,
            };
        }

        return $price;
    }
}
