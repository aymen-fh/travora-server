<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\FulfillmentStatus;
use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Order>
 */
final class OrderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $subtotal = 10000;
        $discount = 0;
        $tax = 0;

        return [
            'user_id' => User::factory(),
            'order_number' => 'TRV-'.Str::upper(Str::random(10)),
            'status' => OrderStatus::Pending,
            'payment_status' => OrderPaymentStatus::Pending,
            'fulfillment_status' => FulfillmentStatus::Pending,
            'currency' => 'LYD',
            'subtotal_minor' => $subtotal,
            'discount_minor' => $discount,
            'tax_minor' => $tax,
            'total_minor' => $subtotal - $discount + $tax,
            'paid_minor' => 0,
        ];
    }
}
