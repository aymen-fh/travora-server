<?php

declare(strict_types=1);

use App\Enums\OrderPaymentStatus;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Enums\RoleName;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\OtpVerification;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Promotion;
use App\Models\User;
use App\Models\Wallet;
use App\Notifications\PasswordResetCodeNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;

function paidPayment(User $user, int $amount = 4500): Payment
{
    $order = Order::factory()->create([
        'user_id' => $user->id,
        'payment_status' => OrderPaymentStatus::Paid,
        'paid_minor' => $amount,
    ]);

    $method = new PaymentMethod;
    $method->user_id = $user->id;
    $method->type = 'wallet';
    $method->save();

    $payment = new Payment;
    $payment->user_id = $user->id;
    $payment->order_id = $order->id;
    $payment->payment_method_id = $method->id;
    $payment->payment_number = 'PAY-'.strtoupper(substr($payment->id ?? bin2hex(random_bytes(3)), 0, 8));
    $payment->type = PaymentType::Charge;
    $payment->status = PaymentStatus::Paid;
    $payment->amount_minor = $amount;
    $payment->refunded_minor = 0;
    $payment->currency = 'LYD';
    $payment->paid_at = now();
    $payment->save();

    return $payment;
}

it('refunds the remaining amount, credits the wallet, and writes an audit log', function (): void {
    $customer = customer('100.000000');
    $payment = paidPayment($customer, 4500);
    Sanctum::actingAs(staff(RoleName::SuperAdmin), ['admin']);

    $this->postJson('/api/v1/admin/payments/'.$payment->id.'/refunds', [
        'reason' => 'customer request',
    ], ['Idempotency-Key' => 'refund-success-1'])
        ->assertOk()
        ->assertJsonPath('data.amount_minor', 4500)
        ->assertJsonPath('data.status', 'completed');

    $payment->refresh();
    expect($payment->status)->toBe(PaymentStatus::Refunded)
        ->and($payment->refunded_minor)->toBe(4500)
        ->and($payment->order->fresh()->payment_status)->toBe(OrderPaymentStatus::Refunded)
        ->and(Wallet::query()->where('user_id', $customer->id)->value('available_balance_minor'))->toBe(10000 + 4500)
        ->and(AuditLog::query()->where('action', 'payment.refunded')->where('entity_id', $payment->id)->exists())->toBeTrue();
});

it('rejects a card refund because no provider refund driver is implemented', function (): void {
    $user = customer();
    $order = Order::factory()->create([
        'user_id' => $user->id,
        'payment_status' => OrderPaymentStatus::Paid,
    ]);
    $payment = new Payment;
    $payment->user_id = $user->id;
    $payment->order_id = $order->id;
    $payment->payment_number = 'PAY-CARD1';
    $payment->type = PaymentType::Charge;
    $payment->status = PaymentStatus::Paid;
    $payment->amount_minor = 4500;
    $payment->refunded_minor = 0;
    $payment->currency = 'LYD';
    $payment->paid_at = now();
    $payment->save();

    Sanctum::actingAs(staff(RoleName::SuperAdmin), ['admin']);

    $this->postJson('/api/v1/admin/payments/'.$payment->id.'/refunds', [
        'reason' => 'card',
    ], ['Idempotency-Key' => 'refund-card'])->assertStatus(422);

    expect($payment->fresh()->status)->toBe(PaymentStatus::Paid);
});

it('rejects a refund above the remaining balance', function (): void {
    $payment = paidPayment(customer(), 4500);
    Sanctum::actingAs(staff(RoleName::SuperAdmin), ['admin']);

    $this->postJson('/api/v1/admin/payments/'.$payment->id.'/refunds', [
        'reason' => 'too much',
        'amount_minor' => 9000,
    ], ['Idempotency-Key' => 'refund-too-much'])->assertStatus(422);
});

it('forbids a refund without the permission and without authentication', function (): void {
    $payment = paidPayment(customer(), 4500);

    $this->postJson('/api/v1/admin/payments/'.$payment->id.'/refunds', [
        'reason' => 'nope',
    ], ['Idempotency-Key' => 'refund-guest'])->assertUnauthorized();

    Sanctum::actingAs(staff(RoleName::Support), ['admin']);
    $this->postJson('/api/v1/admin/payments/'.$payment->id.'/refunds', [
        'reason' => 'nope',
    ], ['Idempotency-Key' => 'refund-support'])->assertForbidden();
});

it('does not create a second refund for the same idempotency key or a refunded payment', function (): void {
    $payment = paidPayment(customer(), 4500);
    Sanctum::actingAs(staff(RoleName::SuperAdmin), ['admin']);

    $this->postJson('/api/v1/admin/payments/'.$payment->id.'/refunds', [
        'reason' => 'once',
    ], ['Idempotency-Key' => 'refund-once'])->assertOk();

    $this->postJson('/api/v1/admin/payments/'.$payment->id.'/refunds', [
        'reason' => 'once',
    ], ['Idempotency-Key' => 'refund-once'])->assertOk();

    expect($payment->refunds()->count())->toBe(1);

    $this->postJson('/api/v1/admin/payments/'.$payment->id.'/refunds', [
        'reason' => 'again',
    ], ['Idempotency-Key' => 'refund-again'])->assertStatus(422);
});

it('verifies a password reset code once and rejects invalid, expired, reused, and exhausted codes', function (): void {
    $user = User::factory()->create(['email' => 'reset@travora.test']);

    Notification::fake();
    $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email])->assertOk();
    Notification::assertSentTo($user, PasswordResetCodeNotification::class);
    expect(OtpVerification::query()->where('target', $user->email)->whereNull('consumed_at')->exists())->toBeTrue();

    $otp = new OtpVerification;
    $otp->user_id = $user->id;
    $otp->channel = 'email';
    $otp->target = 'known@travora.test';
    $otp->purpose = 'password_reset';
    $otp->code_hash = Hash::make('123456');
    $otp->expires_at = now()->addMinutes(10);
    $otp->attempts = 0;
    $otp->save();

    $this->postJson('/api/v1/auth/otp/verify', ['email' => 'known@travora.test', 'code' => '000000'])->assertStatus(422);
    $this->postJson('/api/v1/auth/otp/verify', ['email' => 'known@travora.test', 'code' => '123456'])->assertOk()->assertJsonPath('data.verified', true);
    $this->postJson('/api/v1/auth/otp/verify', ['email' => 'known@travora.test', 'code' => '123456'])->assertStatus(422);

    $expired = new OtpVerification;
    $expired->channel = 'email';
    $expired->target = 'expired@travora.test';
    $expired->purpose = 'password_reset';
    $expired->code_hash = Hash::make('654321');
    $expired->expires_at = now()->subMinute();
    $expired->attempts = 0;
    $expired->save();
    $this->postJson('/api/v1/auth/otp/verify', ['email' => 'expired@travora.test', 'code' => '654321'])->assertStatus(422);

    $limited = new OtpVerification;
    $limited->channel = 'email';
    $limited->target = 'limited@travora.test';
    $limited->purpose = 'password_reset';
    $limited->code_hash = Hash::make('111111');
    $limited->expires_at = now()->addMinutes(10);
    $limited->attempts = 5;
    $limited->save();
    $this->postJson('/api/v1/auth/otp/verify', ['email' => 'limited@travora.test', 'code' => '111111'])->assertStatus(429);
});

it('stores the current admin language and theme and keeps them on the next read', function (): void {
    $admin = staff(RoleName::Admin);
    Sanctum::actingAs($admin, ['admin']);

    $this->getJson('/api/v1/auth/preferences')
        ->assertOk()
        ->assertJsonPath('data.preferred_language', 'en');

    $this->patchJson('/api/v1/auth/preferences', [
        'preferred_language' => 'ar',
        'theme' => 'dark',
    ])->assertOk()->assertJsonPath('data.theme', 'dark');

    $this->getJson('/api/v1/auth/preferences')
        ->assertOk()
        ->assertJsonPath('data.preferred_language', 'ar')
        ->assertJsonPath('data.theme', 'dark');

    expect(AuditLog::query()->where('action', 'user.preferences.updated')->where('entity_id', $admin->id)->exists())->toBeTrue();

    auth()->forgetGuards();
    $this->getJson('/api/v1/auth/preferences')->assertUnauthorized();
});

it('aggregates dashboard counts for the selected dates without using a page of rows', function (): void {
    Order::factory()->count(2)->create();
    Order::factory()->create(['created_at' => now()->subDays(40)]);
    Sanctum::actingAs(staff(RoleName::SuperAdmin), ['admin']);

    $today = $this->getJson('/api/v1/admin/dashboard?range=today')->assertOk();
    expect($today->json('data.orders_total'))->toBe(2)
        ->and(collect($today->json('data.orders_by_day'))->sum('orders'))->toBe(2)
        ->and(array_sum($today->json('data.orders_by_status')))->toBe(2)
        ->and($today->json('data.esim_by_status'))->toBeArray()
        ->and($today->json('data.payment_activity'))->toHaveKeys(['topup', 'purchase', 'refund']);

    $wide = $this->getJson('/api/v1/admin/dashboard?range=custom&from='.now()->subDays(41)->toDateString().'&to='.now()->toDateString());
    expect($wide->json('data.orders_total'))->toBe(3);
});

it('searches several record types for an authorized admin and returns nothing for a short query', function (): void {
    $buyer = User::factory()->create(['email' => 'finder@travora.test']);
    Order::factory()->create(['user_id' => $buyer->id, 'order_number' => 'ORD-FINDER-1']);
    Promotion::query()->create([
        'name' => 'Finder sale',
        'code' => 'FINDER',
        'type' => 'percentage',
        'percentage' => 5,
        'is_active' => true,
    ]);

    $this->getJson('/api/v1/admin/search?q=finder')->assertUnauthorized();

    Sanctum::actingAs(staff(RoleName::SuperAdmin), ['admin']);
    $found = $this->getJson('/api/v1/admin/search?q=finder&per_type=2')->assertOk();
    $types = collect($found->json('data'))->pluck('type')->unique()->sort()->values()->all();
    expect($types)->toContain('user')->toContain('order')->toContain('promotion');
    expect($found->json('data.0'))->toHaveKeys(['type', 'id', 'title', 'subtitle', 'status', 'href']);

    $this->getJson('/api/v1/admin/search?q=f')->assertOk()->assertJsonPath('data', []);

    Sanctum::actingAs(staff(RoleName::Support), ['admin']);
    $limited = collect($this->getJson('/api/v1/admin/search?q=finder')->json('data'))->pluck('type');
    expect($limited)->toContain('user')->not->toContain('promotion');
});
