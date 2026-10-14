<?php

declare(strict_types=1);

use App\Enums\RoleName;
use Laravel\Sanctum\Sanctum;

it('allows super admins to view the dashboard', function (): void {
    Sanctum::actingAs(adminUser(), ['*']);

    $this->getJson('/api/v1/admin/dashboard')->assertOk()->assertJsonPath('success', true);
});

it('forbids customers from admin routes', function (): void {
    Sanctum::actingAs(customer());

    $this->getJson('/api/v1/admin/dashboard')->assertForbidden();
});

it('forbids customers from directly topping up wallet via API', function (): void {
    $user = customer('10.00');
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/wallet/topup', ['amount_minor' => 4000], [
        'Idempotency-Key' => 'customer-wallet-topup-unauthorized',
    ])->assertForbidden();
});

it('allows authorized staff to credit wallet via API', function (): void {
    $admin = adminUser();
    \App\Models\Wallet::factory()->create([
        'user_id' => $admin->id,
        'currency' => 'LYD',
        'available_balance_minor' => 0,
        'status' => \App\Enums\WalletStatus::Active,
    ]);
    Sanctum::actingAs($admin);

    $this->postJson('/api/v1/wallet/topup', ['amount_minor' => 4000], [
        'Idempotency-Key' => 'admin-wallet-topup-1',
    ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.available_balance_minor', 4000);
});
