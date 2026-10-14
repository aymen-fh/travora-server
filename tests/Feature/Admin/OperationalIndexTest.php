<?php

declare(strict_types=1);

use App\Enums\RoleName;
use App\Models\Promotion;
use Laravel\Sanctum\Sanctum;

it('lists a promotion from the database for a staff user who can view products', function (): void {
    $promotion = Promotion::query()->create([
        'name' => 'Libya spring',
        'code' => 'SPRING26',
        'type' => 'percentage',
        'percentage' => 10,
        'is_active' => true,
    ]);

    Sanctum::actingAs(staff(RoleName::SuperAdmin), ['admin']);

    $this->getJson('/api/v1/admin/promotions?search=SPRING26')
        ->assertOk()
        ->assertJsonPath('data.0.id', $promotion->id)
        ->assertJsonPath('data.0.code', 'SPRING26')
        ->assertJsonPath('meta.current_page', 1);
});

it('forbids promotions for support and allows the other operational lists', function (): void {
    Sanctum::actingAs(staff(RoleName::Support), ['admin']);

    $this->getJson('/api/v1/admin/promotions')->assertForbidden();
    $this->getJson('/api/v1/admin/trips')->assertOk()->assertJsonPath('meta.per_page', 20);
    $this->getJson('/api/v1/admin/esim/topups')->assertOk();
    $this->getJson('/api/v1/admin/esim/renewals')->assertOk();
    $this->getJson('/api/v1/admin/notifications')->assertOk();
});

it('lists networks for operators and forbids sim products for finance', function (): void {
    Sanctum::actingAs(staff(RoleName::Operator), ['admin']);
    $this->getJson('/api/v1/admin/network-operators')->assertOk();

    Sanctum::actingAs(staff(RoleName::Finance), ['admin']);
    $this->getJson('/api/v1/admin/sim-products')->assertForbidden();
    $this->getJson('/api/v1/admin/sim-inventory')->assertForbidden();
});
