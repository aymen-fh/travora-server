<?php

declare(strict_types=1);

use App\Domain\References\AssertReference;
use App\Enums\OrderItemType;
use App\Enums\TripItemType;
use App\Models\EsimPlan;
use App\Models\EsimProfile;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Str;

it('accepts an order item only when the referenced plan exists', function (): void {
    $plan = EsimPlan::factory()->create();
    $guard = app(AssertReference::class);

    $guard->orderItem(OrderItemType::EsimPlan, $plan->id);

    expect(fn () => $guard->orderItem(OrderItemType::EsimPlan, (string) Str::uuid()))
        ->toThrow(InvalidArgumentException::class);
});

it('accepts wallet references only for an existing order or payment', function (): void {
    $order = Order::factory()->create();
    $guard = app(AssertReference::class);

    $guard->wallet(Order::class, $order->id);
    $guard->wallet(User::class, $order->user_id);
    $guard->wallet(null, null);

    expect(fn () => $guard->wallet(EsimProfile::class, $order->id))
        ->toThrow(InvalidArgumentException::class);
});

it('checks trip item references without creating a travel workflow', function (): void {
    $guard = app(AssertReference::class);

    $guard->tripItem(TripItemType::Note, null);

    expect(fn () => $guard->tripItem(TripItemType::FlightBooking, (string) Str::uuid()))
        ->toThrow(InvalidArgumentException::class);
});
