<?php

declare(strict_types=1);

use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Enums\RecordStatus;
use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Enums\WebhookEventStatus;
use App\Models\Payment;
use App\Models\Provider;
use App\Models\ProviderCredential;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WebhookEvent;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

/**
 * Release check: a denied sensitive write returns the expected status and
 * leaves operational data unchanged.
 */
it('blocks sensitive writes the role cannot perform without changing data', function (): void {
    $provider = Provider::factory()->create([
        'status' => RecordStatus::Active,
        'code' => 'matrix-provider',
    ]);
    $event = new WebhookEvent;
    $event->provider_id = $provider->id;
    $event->event_id = 'wh-matrix-1';
    $event->event_type = 'ping';
    $event->payload = ['hello' => 'world'];
    $event->payload_hash = hash('sha256', json_encode(['hello' => 'world']));
    $event->signature_valid = true;
    $event->status = WebhookEventStatus::Failed;
    $event->attempt_count = 1;
    $event->received_at = now();
    $event->save();

    $owner = customer();
    $wallet = $owner->wallets()->firstOrFail();
    $payment = new Payment;
    $payment->user_id = $owner->id;
    $payment->payment_number = 'PAY-MATRIX-1';
    $payment->type = PaymentType::Charge;
    $payment->status = PaymentStatus::Paid;
    $payment->amount_minor = 4500;
    $payment->refunded_minor = 0;
    $payment->currency = 'LYD';
    $payment->save();

    $actors = [
        'support' => staff(RoleName::Support),
        'operator' => staff(RoleName::Operator),
        'finance' => staff(RoleName::Finance),
        'admin' => staff(RoleName::Admin),
    ];

    $denied = [
        ['support', 'post', '/api/v1/admin/providers', ['name' => 'Nope', 'code' => 'nope-provider']],
        ['finance', 'post', '/api/v1/admin/providers', ['name' => 'Nope', 'code' => 'nope-provider']],
        ['support', 'patch', "/api/v1/admin/providers/{$provider->id}", ['name' => 'Renamed']],
        ['finance', 'patch', "/api/v1/admin/providers/{$provider->id}", ['name' => 'Renamed']],
        ['support', 'post', "/api/v1/admin/providers/{$provider->id}/disable", ['reason' => 'nope']],
        ['finance', 'post', "/api/v1/admin/providers/{$provider->id}/disable", ['reason' => 'nope']],
        ['support', 'post', "/api/v1/admin/providers/{$provider->id}/sync", []],
        ['finance', 'post', "/api/v1/admin/providers/{$provider->id}/sync", []],
        ['support', 'post', "/api/v1/admin/providers/{$provider->id}/health-check", []],
        ['finance', 'post', "/api/v1/admin/providers/{$provider->id}/health-check", []],
        ['support', 'put', "/api/v1/admin/providers/{$provider->id}/credentials", ['environment' => 'sandbox', 'credential_key' => 'api_token', 'secret_reference' => 'vault://hidden']],
        ['operator', 'put', "/api/v1/admin/providers/{$provider->id}/credentials", ['environment' => 'sandbox', 'credential_key' => 'api_token', 'secret_reference' => 'vault://hidden']],
        ['finance', 'put', "/api/v1/admin/providers/{$provider->id}/credentials", ['environment' => 'sandbox', 'credential_key' => 'api_token', 'secret_reference' => 'vault://hidden']],
        ['admin', 'put', "/api/v1/admin/providers/{$provider->id}/credentials", ['environment' => 'sandbox', 'credential_key' => 'api_token', 'secret_reference' => 'vault://hidden']],
        ['support', 'put', "/api/v1/admin/providers/{$provider->id}/services", ['services' => [['service_type' => 'esim']]]],
        ['operator', 'put', "/api/v1/admin/providers/{$provider->id}/services", ['services' => [['service_type' => 'esim']]]],
        ['finance', 'put', "/api/v1/admin/providers/{$provider->id}/services", ['services' => [['service_type' => 'esim']]]],
        ['support', 'put', "/api/v1/admin/providers/{$provider->id}/countries", ['coverage' => []]],
        ['operator', 'put', "/api/v1/admin/providers/{$provider->id}/countries", ['coverage' => []]],
        ['finance', 'put', "/api/v1/admin/providers/{$provider->id}/countries", ['coverage' => []]],
        ['support', 'post', "/api/v1/admin/webhooks/{$event->id}/retry", []],
        ['finance', 'post', "/api/v1/admin/webhooks/{$event->id}/retry", []],
        ['support', 'post', "/api/v1/admin/payments/{$payment->id}/refunds", ['reason' => 'nope']],
        ['operator', 'post', "/api/v1/admin/payments/{$payment->id}/refunds", ['reason' => 'nope']],
        ['admin', 'post', "/api/v1/admin/payments/{$payment->id}/refunds", ['reason' => 'nope']],
        ['support', 'post', "/api/v1/admin/wallets/{$wallet->id}/adjustments", ['direction' => 'credit', 'amount_minor' => 100, 'reason' => 'nope']],
        ['operator', 'post', "/api/v1/admin/wallets/{$wallet->id}/adjustments", ['direction' => 'credit', 'amount_minor' => 100, 'reason' => 'nope']],
        ['admin', 'post', "/api/v1/admin/wallets/{$wallet->id}/adjustments", ['direction' => 'credit', 'amount_minor' => 100, 'reason' => 'nope']],
        ['support', 'post', '/api/v1/admin/staff', ['name' => 'Escalated', 'email' => 'escalated-matrix@travora.test', 'role' => 'super_admin']],
        ['operator', 'post', '/api/v1/admin/staff', ['name' => 'Escalated', 'email' => 'escalated-matrix@travora.test', 'role' => 'super_admin']],
        ['finance', 'post', '/api/v1/admin/staff', ['name' => 'Escalated', 'email' => 'escalated-matrix@travora.test', 'role' => 'super_admin']],
        ['admin', 'post', '/api/v1/admin/staff', ['name' => 'Escalated', 'email' => 'escalated-matrix@travora.test', 'role' => 'super_admin']],
    ];

    foreach ($denied as [$role, $method, $uri, $body]) {
        $before = operationalFingerprint();
        Sanctum::actingAs($actors[$role], ['admin']);

        $this->json($method, $uri, $body, ['Idempotency-Key' => 'matrix-'.$role.'-'.md5($method.$uri)])
            ->assertForbidden();

        expect(operationalFingerprint())->toBe($before);
    }

    expect($provider->refresh()->status)->toBe(RecordStatus::Active)
        ->and($event->refresh()->status)->toBe(WebhookEventStatus::Failed)
        ->and($payment->refresh()->refunded_minor)->toBe(0)
        ->and($wallet->refresh()->available_balance_minor)->toBe(10000)
        ->and(ProviderCredential::query()->count())->toBe(0)
        ->and(User::query()->where('email', 'escalated-matrix@travora.test')->exists())->toBeFalse();
});

it('rejects a suspended staff token on sensitive writes and deletes that token', function (): void {
    $provider = Provider::factory()->create([
        'status' => RecordStatus::Active,
        'code' => 'matrix-suspended',
    ]);
    $operator = staff(RoleName::Operator);
    $event = new WebhookEvent;
    $event->provider_id = $provider->id;
    $event->event_id = 'wh-matrix-suspended';
    $event->event_type = 'ping';
    $event->payload = ['hello' => 'world'];
    $event->payload_hash = hash('sha256', 'suspended');
    $event->signature_valid = true;
    $event->status = WebhookEventStatus::Failed;
    $event->attempt_count = 1;
    $event->received_at = now();
    $event->save();

    $calls = [
        ['post', "/api/v1/admin/providers/{$provider->id}/disable", ['reason' => 'nope']],
        ['put', "/api/v1/admin/providers/{$provider->id}/credentials", ['environment' => 'sandbox', 'credential_key' => 'api_token', 'secret_reference' => 'vault://hidden']],
        ['put', "/api/v1/admin/providers/{$provider->id}/services", ['services' => [['service_type' => 'esim']]]],
        ['post', "/api/v1/admin/webhooks/{$event->id}/retry", []],
        ['post', '/api/v1/admin/staff', ['name' => 'Nope', 'email' => 'suspended-matrix@travora.test', 'role' => 'admin']],
    ];

    foreach ($calls as [$method, $uri, $body]) {
        $this->flushSession();
        $this->flushHeaders();
        $this->app['auth']->forgetGuards();

        $operator->status = UserStatus::Active;
        $operator->save();
        $operator->tokens()->delete();
        $token = $operator->createToken('portal', ['admin'])->plainTextToken;
        $operator->status = UserStatus::Suspended;
        $operator->save();

        $before = operationalFingerprint();

        $this->withToken($token)->json($method, $uri, $body)->assertUnauthorized();

        expect(operationalFingerprint())->toBe($before)
            ->and($operator->fresh()->tokens()->count())->toBe(0);
    }

    expect($provider->refresh()->status)->toBe(RecordStatus::Active)
        ->and($event->refresh()->status)->toBe(WebhookEventStatus::Failed)
        ->and(User::query()->where('email', 'suspended-matrix@travora.test')->exists())->toBeFalse();
});

/**
 * @return array<string, mixed>
 */
function operationalFingerprint(): array
{
    return [
        'providers' => Provider::query()->orderBy('code')->get()->map(fn (Provider $provider): array => [
            'code' => $provider->code,
            'name' => $provider->name,
            'status' => $provider->status?->value,
        ])->all(),
        'credentials' => ProviderCredential::query()->count(),
        'services' => DB::table('provider_services')->count(),
        'coverage' => DB::table('provider_country_coverage')->count(),
        'webhooks' => WebhookEvent::query()->orderBy('event_id')->get()->map(fn (WebhookEvent $event): array => [
            'event_id' => $event->event_id,
            'status' => $event->status?->value,
        ])->all(),
        'refunded' => Payment::query()->orderBy('payment_number')->pluck('refunded_minor', 'payment_number')->all(),
        'wallets' => Wallet::query()->orderBy('id')->pluck('available_balance_minor', 'id')->all(),
        'users' => User::query()->count(),
        'assignments' => DB::table('model_has_roles')->count(),
    ];
}
