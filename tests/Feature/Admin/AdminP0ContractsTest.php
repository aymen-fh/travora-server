<?php

declare(strict_types=1);

use App\Enums\OrderItemType;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Enums\RecordStatus;
use App\Enums\RefundStatus;
use App\Enums\RoleName;
use App\Enums\SupportPriority;
use App\Enums\SupportTicketStatus;
use App\Models\AuditLog;
use App\Models\Country;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\PaymentAttempt;
use App\Models\PaymentRefund;
use App\Models\Provider;
use App\Models\ProviderProduct;
use App\Models\ProviderService;
use App\Models\SupportTicket;
use Laravel\Sanctum\Sanctum;

it('returns order items on the admin order show contract', function (): void {
    $order = Order::factory()->create();
    $item = new OrderItem;
    $item->order_id = $order->id;
    $item->item_type = OrderItemType::EsimPlan;
    $item->name_snapshot = 'Libya 3GB';
    $item->quantity = 1;
    $item->unit_price_minor = 4500;
    $item->discount_minor = 0;
    $item->tax_minor = 0;
    $item->total_minor = 4500;
    $item->save();

    Sanctum::actingAs(staff(RoleName::Operator), ['*']);

    $this->getJson('/api/v1/admin/orders/'.$order->id)
        ->assertOk()
        ->assertJsonPath('data.items.0.id', $item->id)
        ->assertJsonPath('data.items.0.item_type', OrderItemType::EsimPlan->value)
        ->assertJsonPath('data.items.0.name_snapshot', 'Libya 3GB')
        ->assertJsonPath('data.items.0.unit_price_minor', 4500)
        ->assertJsonPath('data.items.0.total_minor', 4500);
});

it('exposes provider services products and coverage without raw payloads', function (): void {
    $provider = Provider::factory()->create();
    $country = Country::factory()->create(['iso2' => 'LY', 'iso3' => 'LBY']);

    $service = new ProviderService;
    $service->provider_id = $provider->id;
    $service->service_type = 'esim';
    $service->is_enabled = true;
    $service->priority = 10;
    $service->config = ['secret' => 'do-not-leak'];
    $service->save();

    $product = new ProviderProduct;
    $product->provider_id = $provider->id;
    $product->product_type = 'esim';
    $product->external_product_id = 'fake-ly-3gb';
    $product->name = 'Libya 3GB';
    $product->status = RecordStatus::Active;
    $product->raw_data = ['cost_minor' => 900, 'api_token' => 'hidden'];
    $product->save();

    Sanctum::actingAs(staff(RoleName::Operator), ['*']);

    $services = $this->getJson('/api/v1/admin/providers/'.$provider->id.'/services');
    $services->assertOk()
        ->assertJsonPath('data.0.service_type', 'esim')
        ->assertJsonPath('data.0.is_enabled', true)
        ->assertJsonMissingPath('data.0.config');

    expect(json_encode($services->json()))->not->toContain('do-not-leak');

    $products = $this->getJson('/api/v1/admin/providers/'.$provider->id.'/products');
    $products->assertOk()
        ->assertJsonPath('data.0.external_product_id', 'fake-ly-3gb')
        ->assertJsonMissingPath('data.0.raw_data');

    expect(json_encode($products->json()))->not->toContain('hidden');

    Sanctum::actingAs(staff(RoleName::Admin), ['*']);

    $this->putJson('/api/v1/admin/providers/'.$provider->id.'/services', [
        'services' => [
            ['service_type' => 'esim', 'is_enabled' => false, 'priority' => 5],
            ['service_type' => 'sim', 'is_enabled' => true, 'priority' => 20],
        ],
    ])->assertOk()
        ->assertJsonPath('data.0.service_type', 'esim')
        ->assertJsonPath('data.0.is_enabled', false);

    $this->putJson('/api/v1/admin/providers/'.$provider->id.'/countries', [
        'coverage' => [
            ['country_id' => $country->id, 'service_type' => 'esim', 'status' => RecordStatus::Active->value],
        ],
    ])->assertOk()
        ->assertJsonPath('data.0.country.iso2', 'LY')
        ->assertJsonPath('data.0.service_type', 'esim');

    $this->getJson('/api/v1/admin/providers/'.$provider->id.'/countries')
        ->assertOk()
        ->assertJsonPath('data.0.country_id', $country->id);
});

it('forbids operator from mutating provider services and coverage', function (): void {
    $provider = Provider::factory()->create();
    Sanctum::actingAs(staff(RoleName::Operator), ['*']);

    $this->putJson('/api/v1/admin/providers/'.$provider->id.'/services', [
        'services' => [['service_type' => 'esim']],
    ])->assertForbidden();

    $this->putJson('/api/v1/admin/providers/'.$provider->id.'/countries', [
        'coverage' => [['country_id' => Country::factory()->create()->id, 'service_type' => 'esim']],
    ])->assertForbidden();
});

it('lists payments attempts and refunds without provider payloads', function (): void {
    $user = customer();
    $order = Order::factory()->create(['user_id' => $user->id]);

    $payment = new Payment;
    $payment->user_id = $user->id;
    $payment->order_id = $order->id;
    $payment->payment_number = 'PAY-ADMIN1';
    $payment->type = PaymentType::Charge;
    $payment->status = PaymentStatus::Paid;
    $payment->amount_minor = 4500;
    $payment->refunded_minor = 0;
    $payment->currency = 'LYD';
    $payment->paid_at = now();
    $payment->save();

    $attempt = new PaymentAttempt;
    $attempt->payment_id = $payment->id;
    $attempt->attempt_number = 1;
    $attempt->status = PaymentStatus::Paid;
    $attempt->response_code = '00';
    $attempt->response_message = 'captured';
    $attempt->request_payload = ['secret' => 'card-pan'];
    $attempt->response_payload = ['raw' => 'gateway-body'];
    $attempt->save();

    $refund = new PaymentRefund;
    $refund->payment_id = $payment->id;
    $refund->amount_minor = 4500;
    $refund->currency = 'LYD';
    $refund->status = RefundStatus::Completed;
    $refund->reason = 'order cancelled';
    $refund->save();

    Sanctum::actingAs(staff(RoleName::Finance), ['*']);

    $this->getJson('/api/v1/admin/payments')
        ->assertOk()
        ->assertJsonPath('data.0.payment_number', 'PAY-ADMIN1')
        ->assertJsonPath('data.0.status', PaymentStatus::Paid->value)
        ->assertJsonPath('data.0.amount_minor', 4500);

    $show = $this->getJson('/api/v1/admin/payments/'.$payment->id);
    $show->assertOk()->assertJsonPath('data.order.order_number', $order->order_number);

    $attempts = $this->getJson('/api/v1/admin/payments/'.$payment->id.'/attempts');
    $attempts->assertOk()
        ->assertJsonPath('data.0.attempt_number', 1)
        ->assertJsonMissingPath('data.0.request_payload')
        ->assertJsonMissingPath('data.0.response_payload');

    expect(json_encode($attempts->json()))->not->toContain('card-pan');

    $this->getJson('/api/v1/admin/payments/'.$payment->id.'/refunds')
        ->assertOk()
        ->assertJsonPath('data.0.status', RefundStatus::Completed->value)
        ->assertJsonPath('data.0.amount_minor', 4500);

    $this->getJson('/api/v1/admin/refunds')
        ->assertOk()
        ->assertJsonPath('data.0.payment_id', $payment->id);
});

it('forbids support from the admin payment contract', function (): void {
    Sanctum::actingAs(staff(RoleName::Support), ['*']);

    $this->getJson('/api/v1/admin/payments')->assertForbidden();
    $this->getJson('/api/v1/admin/refunds')->assertForbidden();
});

it('lets support list assign reply and close tickets across customers', function (): void {
    $owner = customer();
    $agent = staff(RoleName::Support);

    $ticket = new SupportTicket;
    $ticket->user_id = $owner->id;
    $ticket->ticket_number = 'SUP-ADMIN1';
    $ticket->category = 'esim';
    $ticket->subject = 'Cannot install eSIM';
    $ticket->status = SupportTicketStatus::Open;
    $ticket->priority = SupportPriority::High;
    $ticket->save();

    Sanctum::actingAs($agent, ['*']);

    $this->getJson('/api/v1/admin/support/tickets')
        ->assertOk()
        ->assertJsonPath('data.0.ticket_number', 'SUP-ADMIN1')
        ->assertJsonPath('data.0.user.email', $owner->email);

    $this->postJson('/api/v1/admin/support/tickets/'.$ticket->id.'/assign', [
        'assigned_to' => $agent->id,
    ])->assertOk()
        ->assertJsonPath('data.assigned_to', $agent->id);

    $this->postJson('/api/v1/admin/support/tickets/'.$ticket->id.'/messages', [
        'body' => 'Please retry installation.',
    ])->assertOk()
        ->assertJsonPath('data.status', SupportTicketStatus::PendingCustomer->value)
        ->assertJsonPath('data.messages.0.sender_type', 'support')
        ->assertJsonPath('data.messages.0.message', 'Please retry installation.');

    $this->postJson('/api/v1/admin/support/tickets/'.$ticket->id.'/close', [
        'reason' => 'resolved with customer',
    ])->assertOk()
        ->assertJsonPath('data.status', SupportTicketStatus::Closed->value);

    expect(AuditLog::query()->where('action', 'support.ticket.closed')->where('entity_id', $ticket->id)->exists())->toBeTrue();
});

it('forbids finance from the admin support contract', function (): void {
    Sanctum::actingAs(staff(RoleName::Finance), ['*']);

    $this->getJson('/api/v1/admin/support/tickets')->assertForbidden();
});
