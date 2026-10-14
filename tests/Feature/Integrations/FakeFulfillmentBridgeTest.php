<?php

declare(strict_types=1);

use App\Actions\Orders\ApplyEsimFulfillment;
use App\Actions\Orders\CreateOrder;
use App\Actions\Orders\FulfillOrder;
use App\Actions\Orders\PayOrderWithWallet;
use App\Enums\EsimProfileStatus;
use App\Enums\OrderStatus;
use App\Integrations\Esim\DTOs\PurchaseRequest;
use App\Integrations\Esim\Manager\ProviderManager;
use App\Models\Provider;
use Illuminate\Support\Facades\Event;

it('applies a FakeProvider purchase DTO through domain fulfillment', function (): void {
    Event::fake();

    $provider = Provider::factory()->fake()->create();
    $plan = pricedPlan(5000);
    $plan->provider_id = $provider->id;
    $plan->save();

    $user = customer('80.00');
    $order = app(CreateOrder::class)($user, $plan, 'bridge-order');
    $paid = app(PayOrderWithWallet::class)($user, $order, $user->wallets()->firstOrFail());
    $fulfilling = app(FulfillOrder::class)($paid);

    $purchase = app(ProviderManager::class)
        ->driver($provider)
        ->purchase(new PurchaseRequest('fake-tr-10gb', 'bridge-purchase'));

    expect($purchase->fulfillment)->not->toBeNull();

    $completed = app(ApplyEsimFulfillment::class)($fulfilling, $purchase->fulfillment);

    expect($completed->status)->toBe(OrderStatus::Completed);
    expect($completed->items->first()->esimProfile?->status)->toBe(EsimProfileStatus::Ready);
    expect($completed->items->first()->esimProfile?->external_profile_id)->toStartWith('fake-esim-');
});
