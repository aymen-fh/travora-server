<?php

declare(strict_types=1);

use Laravel\Sanctum\Sanctum;

it('strictly forbids standard customers from directly crediting their wallet balance', function (): void {
    $customer = customer('10.00');
    Sanctum::actingAs($customer);

    $this->postJson('/api/v1/wallet/topup', [
        'amount_minor' => 100000,
        'currency' => 'LYD',
    ], [
        'Idempotency-Key' => 'malicious-mint-attempt-001',
    ])->assertForbidden();
});

it('rejects wallet top-up missing an idempotency key with 428', function (): void {
    $customer = customer('10.00');
    Sanctum::actingAs($customer);

    $this->postJson('/api/v1/wallet/topup', [
        'amount_minor' => 5000,
        'currency' => 'LYD',
    ])->assertStatus(428);
});

it('allows authorized staff to credit wallet directly with required idempotency key', function (): void {
    $staff = adminUser();
    \App\Models\Wallet::factory()->create([
        'user_id' => $staff->id,
        'currency' => 'LYD',
        'available_balance_minor' => 0,
        'status' => \App\Enums\WalletStatus::Active,
    ]);
    Sanctum::actingAs($staff);

    $this->postJson('/api/v1/wallet/topup', [
        'amount_minor' => 5000,
        'currency' => 'LYD',
    ], [
        'Idempotency-Key' => 'authorized-staff-credit-001',
    ])->assertOk()
        ->assertJsonPath('success', true);
});
