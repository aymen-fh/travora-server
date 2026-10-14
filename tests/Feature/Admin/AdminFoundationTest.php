<?php

declare(strict_types=1);

use App\Enums\OrderPaymentStatus;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Enums\WalletDirection;
use App\Enums\WebhookEventStatus;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Provider;
use App\Models\ProviderCredential;
use App\Models\User;
use App\Models\WebhookEvent;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

it('rejects guests from admin routes with a 401 envelope', function (): void {
    $this->getJson('/api/v1/admin/dashboard')
        ->assertUnauthorized()
        ->assertJsonPath('success', false)
        ->assertJsonPath('code', 'UNAUTHENTICATED');
});

it('rejects customers from admin routes with a 403 envelope', function (): void {
    Sanctum::actingAs(customer());

    $this->getJson('/api/v1/admin/dashboard')
        ->assertForbidden()
        ->assertJsonPath('success', false)
        ->assertJsonPath('code', 'FORBIDDEN');
});

it('lets super_admin bypass permission checks after sanctum authentication', function (): void {
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
    Role::findByName(RoleName::SuperAdmin->value)->syncPermissions([]);

    $user = User::factory()->create();
    $user->assignRole(RoleName::SuperAdmin->value);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/admin/dashboard')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.wallet_available_minor', 0);
});

it('enforces the locked permission matrix', function (RoleName $role, string $method, string $uri, int $status): void {
    Sanctum::actingAs(staff($role), ['*']);

    $this->json($method, $uri)->assertStatus($status);
})->with([
    'support users' => [RoleName::Support, 'GET', '/api/v1/admin/users', 200],
    'support orders' => [RoleName::Support, 'GET', '/api/v1/admin/orders', 200],
    'support wallets' => [RoleName::Support, 'GET', '/api/v1/admin/wallets', 403],
    'support providers' => [RoleName::Support, 'GET', '/api/v1/admin/providers', 403],
    'support webhooks' => [RoleName::Support, 'GET', '/api/v1/admin/webhooks', 403],
    'finance users' => [RoleName::Finance, 'GET', '/api/v1/admin/users', 403],
    'finance orders' => [RoleName::Finance, 'GET', '/api/v1/admin/orders', 200],
    'finance wallets' => [RoleName::Finance, 'GET', '/api/v1/admin/wallets', 200],
    'finance payments' => [RoleName::Finance, 'GET', '/api/v1/admin/payments', 200],
    'finance support' => [RoleName::Finance, 'GET', '/api/v1/admin/support/tickets', 403],
    'finance providers' => [RoleName::Finance, 'GET', '/api/v1/admin/providers', 403],
    'finance webhooks' => [RoleName::Finance, 'GET', '/api/v1/admin/webhooks', 403],
    'support tickets' => [RoleName::Support, 'GET', '/api/v1/admin/support/tickets', 200],
    'support payments' => [RoleName::Support, 'GET', '/api/v1/admin/payments', 403],
    'operator users' => [RoleName::Operator, 'GET', '/api/v1/admin/users', 403],
    'operator orders' => [RoleName::Operator, 'GET', '/api/v1/admin/orders', 200],
    'operator wallets' => [RoleName::Operator, 'GET', '/api/v1/admin/wallets', 403],
    'operator providers' => [RoleName::Operator, 'GET', '/api/v1/admin/providers', 200],
    'operator webhooks' => [RoleName::Operator, 'GET', '/api/v1/admin/webhooks', 200],
    'admin users' => [RoleName::Admin, 'GET', '/api/v1/admin/users', 200],
    'admin wallets' => [RoleName::Admin, 'GET', '/api/v1/admin/wallets', 403],
]);

it('returns an ERD v2 dashboard without decimal wallet fields', function (): void {
    Sanctum::actingAs(staff(RoleName::Finance), ['*']);

    $response = $this->getJson('/api/v1/admin/dashboard?range=30d');

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'orders_total',
                'revenue_minor',
                'wallet_available_minor',
                'wallet_held_minor',
            ],
            'meta',
        ]);

    expect($response->json('data'))->not->toHaveKey('revenue')
        ->and($response->json('data'))->not->toHaveKey('users_total')
        ->and($response->json())->not->toHaveKey('data.providers');
});

it('returns daily paid revenue that sums to the period total', function (): void {
    Sanctum::actingAs(staff(RoleName::Finance), ['*']);

    $paidOn = now()->subDays(3)->setTime(15, 0);
    $paidOrder = Order::factory()->create([
        'payment_status' => OrderPaymentStatus::Paid,
        'paid_minor' => 4200,
        'total_minor' => 4200,
        'subtotal_minor' => 4200,
        'created_at' => $paidOn,
    ]);
    $payment = new Payment;
    $payment->user_id = $paidOrder->user_id;
    $payment->order_id = $paidOrder->id;
    $payment->payment_number = 'PAY-DASH-4200';
    $payment->type = PaymentType::Charge;
    $payment->status = PaymentStatus::Paid;
    $payment->amount_minor = 4200;
    $payment->currency = 'LYD';
    $payment->save();
    $allocation = new PaymentAllocation;
    $allocation->order_id = $paidOrder->id;
    $allocation->payment_id = $payment->id;
    $allocation->source_type = 'wallet';
    $allocation->amount_minor = 4200;
    $allocation->currency = 'LYD';
    $allocation->status = PaymentStatus::Paid;
    $allocation->save();
    Order::factory()->create([
        'payment_status' => OrderPaymentStatus::Pending,
        'paid_minor' => 9900,
        'total_minor' => 9900,
        'subtotal_minor' => 9900,
        'created_at' => $paidOn,
    ]);

    $response = $this->getJson('/api/v1/admin/dashboard?range=7d')->assertOk();
    $series = collect($response->json('data.revenue_by_day'));

    expect($series)->not->toBeEmpty()
        ->and($series->sum('revenue_minor'))->toBe($response->json('data.revenue_minor'))
        ->and($series->firstWhere('date', $paidOn->toDateString())['revenue_minor'])->toBe(4200);
});

it('suspends a user through the thin action and writes audit', function (): void {
    $admin = staff(RoleName::Admin);
    $target = customer();
    Sanctum::actingAs($admin, ['*']);

    $this->postJson('/api/v1/admin/users/'.$target->id.'/suspend', ['reason' => 'abuse'])
        ->assertOk()
        ->assertJsonPath('data.status', UserStatus::Suspended->value);

    expect($target->refresh()->status)->toBe(UserStatus::Suspended);
    expect(AuditLog::query()->where('action', 'user.suspended')->where('entity_id', $target->id)->exists())->toBeTrue();
});

it('never returns raw provider credentials, even for super_admin', function (): void {
    $provider = Provider::factory()->fake()->create();
    Sanctum::actingAs(staff(RoleName::SuperAdmin), ['*']);

    $put = $this->putJson('/api/v1/admin/providers/'.$provider->id.'/credentials', [
        'environment' => 'sandbox',
        'credential_key' => 'api_token',
        'secret_reference' => 'vault://esim/super-secret-token',
    ]);

    $put->assertOk()
        ->assertJsonPath('data.configured', true)
        ->assertJsonPath('data.masked', true)
        ->assertJsonPath('data.credential_key', 'api_token')
        ->assertJsonMissingPath('data.secret_reference')
        ->assertJsonMissingPath('data.api_key');

    expect($put->json('data.public_reference'))->toStartWith('provider:');
    expect(json_encode($put->json()))->not->toContain('vault://esim/super-secret-token');

    $get = $this->getJson('/api/v1/admin/providers/'.$provider->id.'/credentials');
    $get->assertOk()
        ->assertJsonPath('data.0.masked', true)
        ->assertJsonMissingPath('data.0.secret_reference');

    expect(json_encode($get->json()))->not->toContain('vault://esim/super-secret-token');
    expect(ProviderCredential::query()->where('provider_id', $provider->id)->count())->toBe(1);
    expect(ProviderCredential::query()->first()->secret_reference)->toBe('vault://esim/super-secret-token');
});

it('forbids admin and operator from managing credentials', function (): void {
    $provider = Provider::factory()->create();

    Sanctum::actingAs(staff(RoleName::Admin), ['*']);
    $this->putJson('/api/v1/admin/providers/'.$provider->id.'/credentials', [
        'environment' => 'sandbox',
        'credential_key' => 'api_token',
        'secret_reference' => 'vault://hidden',
    ])->assertForbidden();

    Sanctum::actingAs(staff(RoleName::Operator), ['*']);
    $this->putJson('/api/v1/admin/providers/'.$provider->id.'/credentials', [
        'environment' => 'sandbox',
        'credential_key' => 'api_token',
        'secret_reference' => 'vault://hidden',
    ])->assertForbidden();
});

it('queues provider sync and health jobs', function (): void {
    Queue::fake();
    $provider = Provider::factory()->create();
    Sanctum::actingAs(staff(RoleName::Operator), ['*']);

    $this->postJson('/api/v1/admin/providers/'.$provider->id.'/sync')
        ->assertAccepted()
        ->assertJsonPath('success', true);

    $this->postJson('/api/v1/admin/providers/'.$provider->id.'/health-check')
        ->assertAccepted();
});

it('adjusts a wallet through CreditWallet and writes audit', function (): void {
    $owner = customer('10.00');
    $wallet = $owner->wallets()->firstOrFail();
    Sanctum::actingAs(staff(RoleName::Finance), ['*']);

    $this->postJson('/api/v1/admin/wallets/'.$wallet->id.'/adjustments', [
        'direction' => WalletDirection::Credit->value,
        'amount_minor' => 2500,
        'reason' => 'manual correction',
        'idempotency_key' => 'adj-1',
    ])->assertOk()
        ->assertJsonPath('success', true);

    expect($wallet->refresh()->available_balance_minor)->toBe(3500);
    expect(AuditLog::query()->where('action', 'wallet.adjusted')->where('entity_id', $wallet->id)->exists())->toBeTrue();
});

it('retries a webhook without changing Phase 9 statuses', function (): void {
    Queue::fake();
    $provider = Provider::factory()->fake()->create();
    $event = new WebhookEvent;
    $event->provider_id = $provider->id;
    $event->event_id = 'wh-admin-1';
    $event->event_type = 'ping';
    $event->payload = ['hello' => 'world'];
    $event->payload_hash = hash('sha256', json_encode(['hello' => 'world']));
    $event->signature_valid = true;
    $event->status = WebhookEventStatus::Failed;
    $event->attempt_count = 1;
    $event->received_at = now();
    $event->save();

    Sanctum::actingAs(staff(RoleName::Operator), ['*']);

    $this->postJson('/api/v1/admin/webhooks/'.$event->id.'/retry')
        ->assertOk()
        ->assertJsonPath('data.status', WebhookEventStatus::Pending->value);

    expect($event->refresh()->status->value)->toBeIn(['pending', 'processed', 'ignored', 'failed', 'processing']);
});
