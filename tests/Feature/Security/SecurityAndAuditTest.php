<?php

declare(strict_types=1);

use App\Actions\Audit\RecordAuditLog;
use App\Enums\EsimProfileStatus;
use App\Enums\RoleName;
use App\Integrations\Esim\Support\SecretMasker;
use App\Models\AuditLog;
use App\Models\EsimPlan;
use App\Models\EsimProfile;
use App\Models\Provider;
use App\Models\User;
use App\Support\Auth\TokenAbilities;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;

it('issues a mobile-only token that cannot call admin routes', function (): void {
    $this->postJson('/api/v1/auth/register', [
        'name' => 'Mobile User',
        'email' => 'mobile-token@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertCreated();

    $login = $this->postJson('/api/v1/auth/login', [
        'email' => 'mobile-token@example.com',
        'password' => 'password123',
    ])->assertOk();

    $plain = $login->json('data.token');
    $token = PersonalAccessToken::findToken($plain);

    expect($token?->abilities)->toBe([TokenAbilities::Mobile]);

    $this->getJson('/api/v1/wallet', [
        'Authorization' => 'Bearer '.$plain,
    ])->assertOk();

    $this->getJson('/api/v1/admin/dashboard', [
        'Authorization' => 'Bearer '.$plain,
    ])->assertForbidden()
        ->assertJsonPath('success', false)
        ->assertJsonPath('code', 'FORBIDDEN');
});

it('rejects a staff token that only has the mobile ability', function (): void {
    $staff = staff(RoleName::Operator);
    $plain = $staff->createToken('phone', [TokenAbilities::Mobile])->plainTextToken;

    $this->getJson('/api/v1/admin/dashboard', [
        'Authorization' => 'Bearer '.$plain,
    ])->assertForbidden()
        ->assertJsonPath('success', false)
        ->assertJsonPath('code', 'FORBIDDEN');
});

it('issues admin ability on staff login', function (): void {
    $staff = staff(RoleName::Operator);

    $login = $this->postJson('/api/v1/auth/login', [
        'email' => $staff->email,
        'password' => 'password',
    ])->assertOk();

    $token = PersonalAccessToken::findToken($login->json('data.token'));

    expect($token?->abilities)->toBe([TokenAbilities::Admin, TokenAbilities::Mobile]);
});

it('conflicts when an idempotency key is reused with a different payload', function (): void {
    Event::fake();
    Sanctum::actingAs(customer('80.00'));
    $provider = Provider::factory()->fake()->create();
    $first = mappedPlanOnProvider($provider, 5000, 'fake-tr-10gb');
    $second = mappedPlanOnProvider($provider, 2500, 'fake-eu-5gb');

    $this->postJson('/api/v1/orders', [
        'plan_id' => $first->id,
        'quantity' => 1,
    ], [
        'Idempotency-Key' => 'checkout-conflict',
    ])->assertCreated();

    $this->postJson('/api/v1/orders', [
        'plan_id' => $second->id,
        'quantity' => 1,
    ], [
        'Idempotency-Key' => 'checkout-conflict',
    ])->assertStatus(409)
        ->assertJsonPath('success', false)
        ->assertJsonPath('code', 'IDEMPOTENCY_CONFLICT');
});

it('masks secrets written through RecordAuditLog', function (): void {
    $actor = staff(RoleName::SuperAdmin);

    $provider = Provider::factory()->create();

    $log = app(RecordAuditLog::class)(
        'provider.credentials.updated',
        $actor,
        Provider::class,
        $provider->id,
        null,
        [
            'secret_reference' => 'vault://esim/super-secret-token',
            'activation_code' => 'LPA:1$secret$matching',
            'status' => 'stored',
        ],
    );

    expect($log->new_values['secret_reference'])->toBe('***')
        ->and($log->new_values['activation_code'])->toBe('***')
        ->and($log->new_values['status'])->toBe('stored')
        ->and(json_encode($log->new_values))->not->toContain('vault://esim/super-secret-token');
});

it('audits provider sync and health queueing', function (): void {
    Queue::fake();
    $provider = Provider::factory()->create();
    Sanctum::actingAs(staff(RoleName::Operator), ['*']);

    $this->postJson('/api/v1/admin/providers/'.$provider->id.'/sync')->assertAccepted();
    $this->postJson('/api/v1/admin/providers/'.$provider->id.'/health-check')->assertAccepted();

    expect(AuditLog::query()->where('action', 'provider.sync.queued')->where('entity_id', $provider->id)->exists())->toBeTrue();
    expect(AuditLog::query()->where('action', 'provider.health_check.queued')->where('entity_id', $provider->id)->exists())->toBeTrue();
});

it('audits admin session revoke without leaking the token secret', function (): void {
    $target = customer();
    $created = $target->createToken('phone', [TokenAbilities::Mobile]);
    Sanctum::actingAs(staff(RoleName::Admin), ['*']);

    $this->deleteJson('/api/v1/admin/users/'.$target->id.'/sessions/'.$created->accessToken->id)
        ->assertOk();

    $log = AuditLog::query()->where('action', 'user.session.revoked')->where('entity_id', $target->id)->first();

    expect($log)->not->toBeNull()
        ->and(json_encode($log?->new_values))->not->toContain($created->plainTextToken)
        ->and($target->tokens()->count())->toBe(0);
});

it('does not expose encrypted eSIM fields on the admin profile resource', function (): void {
    $plan = EsimPlan::factory()->create();
    $profile = new EsimProfile;
    $profile->user_id = User::factory()->create()->id;
    $profile->plan_id = $plan->id;
    $profile->provider_id = $plan->provider_id;
    $profile->status = EsimProfileStatus::Purchased;
    $profile->activation_code_encrypted = 'LPA:1$hidden$matching';
    $profile->confirmation_code_encrypted = 'CONF-SECRET';
    $profile->save();

    Sanctum::actingAs(staff(RoleName::Operator), ['*']);

    $response = $this->getJson('/api/v1/admin/esim/profiles/'.$profile->id);

    $response->assertOk()
        ->assertJsonMissingPath('data.activation_code_encrypted')
        ->assertJsonMissingPath('data.confirmation_code_encrypted')
        ->assertJsonMissingPath('data.activation_code');

    expect(json_encode($response->json()))->not->toContain('CONF-SECRET')
        ->and(json_encode($response->json()))->not->toContain('LPA:1$hidden$matching');
});

it('masks nested sensitive keys', function (): void {
    $masked = SecretMasker::mask([
        'headers' => ['Authorization' => 'Bearer abc'],
        'otp_code' => '123456',
        'ok' => true,
    ]);

    expect($masked['headers']['Authorization'])->toBe('***')
        ->and($masked['otp_code'])->toBe('***')
        ->and($masked['ok'])->toBeTrue();
});
