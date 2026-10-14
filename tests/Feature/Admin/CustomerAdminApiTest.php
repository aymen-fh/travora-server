<?php

declare(strict_types=1);

use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\User;
use App\Models\UserProfile;
use Laravel\Sanctum\Sanctum;

it('returns customer details including created_at and profile in AdminUserResource', function (): void {
    $customer = User::factory()->create([
        'email' => 'customer.test@travora.example',
        'phone' => '+218911112233',
        'status' => UserStatus::Active,
    ]);

    $profile = new UserProfile([
        'first_name' => 'Fatima',
        'last_name' => 'Mansoor',
        'display_name' => 'Fatima M',
        'preferred_language' => 'ar',
        'timezone' => 'Africa/Tripoli',
    ]);
    $profile->user_id = $customer->id;
    $profile->save();

    Sanctum::actingAs(staff(RoleName::Support), ['*']);

    $response = $this->getJson('/api/v1/admin/users/'.$customer->id);

    $response->assertOk()
        ->assertJsonPath('data.id', $customer->id)
        ->assertJsonPath('data.name', 'Fatima M')
        ->assertJsonPath('data.first_name', 'Fatima')
        ->assertJsonPath('data.last_name', 'Mansoor')
        ->assertJsonPath('data.display_name', 'Fatima M')
        ->assertJsonPath('data.email', 'customer.test@travora.example')
        ->assertJsonPath('data.phone', '+218911112233')
        ->assertJsonPath('data.status', 'active')
        ->assertJsonPath('data.preferred_language', 'ar')
        ->assertJsonPath('data.timezone', 'Africa/Tripoli');

    expect($response->json('data.created_at'))->not->toBeNull();
});

it('supports server-side search by id, name, phone, and email in users list', function (): void {
    $customer = User::factory()->create([
        'email' => 'tariq.special@travora.example',
        'phone' => '+218929988776',
        'status' => UserStatus::Active,
    ]);

    $profile = new UserProfile([
        'first_name' => 'Tariq',
        'last_name' => 'Zayd',
        'display_name' => 'Tariq Z',
    ]);
    $profile->user_id = $customer->id;
    $profile->save();

    Sanctum::actingAs(staff(RoleName::Support), ['*']);

    // Search by email
    $this->getJson('/api/v1/admin/users?search=tariq.special')
        ->assertOk()
        ->assertJsonPath('data.0.id', $customer->id);

    // Search by phone
    $this->getJson('/api/v1/admin/users?search=929988776')
        ->assertOk()
        ->assertJsonPath('data.0.id', $customer->id);

    // Search by first name
    $this->getJson('/api/v1/admin/users?search=Tariq')
        ->assertOk()
        ->assertJsonPath('data.0.id', $customer->id);

    // Search by id
    $this->getJson('/api/v1/admin/users?search='.$customer->id)
        ->assertOk()
        ->assertJsonPath('data.0.id', $customer->id);
});

it('filters orders by user_id on the admin orders endpoint', function (): void {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    $order1 = Order::factory()->create(['user_id' => $user1->id]);
    $order2 = Order::factory()->create(['user_id' => $user2->id]);

    Sanctum::actingAs(staff(RoleName::Support), ['*']);

    $response = $this->getJson('/api/v1/admin/orders?user_id='.$user1->id);

    $response->assertOk();
    $ids = collect($response->json('data'))->pluck('id')->all();
    expect($ids)->toContain($order1->id)
        ->and($ids)->not->toContain($order2->id);
});

it('filters audit logs by entity_id on the admin audit-logs endpoint', function (): void {
    $user = User::factory()->create();

    $log1 = AuditLog::create([
        'action' => 'user.updated',
        'entity_type' => User::class,
        'entity_id' => $user->id,
        'old_values' => ['phone' => null],
        'new_values' => ['phone' => '+218910000000'],
    ]);

    $log2 = AuditLog::create([
        'action' => 'provider.updated',
        'entity_type' => 'App\Models\Provider',
        'entity_id' => 'some-other-uuid',
    ]);

    Sanctum::actingAs(staff(RoleName::Admin), ['*']);

    $response = $this->getJson('/api/v1/admin/audit-logs?entity_id='.$user->id);

    $response->assertOk();
    $ids = collect($response->json('data'))->pluck('id')->all();
    expect($ids)->toContain($log1->id)
        ->and($ids)->not->toContain($log2->id);
});

it('forbids staff without wallet permissions from accessing wallet endpoints directly', function (): void {
    $customer = User::factory()->create();
    $wallet = \App\Models\Wallet::factory()->create(['user_id' => $customer->id]);

    // Support staff lacks wallets.view
    Sanctum::actingAs(staff(RoleName::Support), ['*']);

    // Direct API call to wallets index -> 403 Forbidden
    $this->getJson('/api/v1/admin/wallets')
        ->assertForbidden();

    // Direct API call to wallet transactions -> 403 Forbidden
    $this->getJson('/api/v1/admin/wallets/'.$wallet->id.'/transactions')
        ->assertForbidden();

    // In customer show endpoint, wallets key is completely omitted for unauthorized staff
    $this->getJson('/api/v1/admin/users/'.$customer->id)
        ->assertOk()
        ->assertJsonMissingPath('data.wallets');

    // Finance staff has wallets.view and can access
    Sanctum::actingAs(staff(RoleName::Finance), ['*']);

    $this->getJson('/api/v1/admin/wallets')
        ->assertOk();

    $this->getJson('/api/v1/admin/wallets/'.$wallet->id.'/transactions')
        ->assertOk();
});

