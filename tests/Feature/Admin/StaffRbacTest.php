<?php

declare(strict_types=1);

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\AuditLog;
use App\Models\User;
use Database\Seeders\MockStaffSeeder;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

it('gives each staff role its own access and blocks the others', function (RoleName $role, string $uri, int $status): void {
    Sanctum::actingAs(staff($role), ['*']);

    $this->getJson($uri)->assertStatus($status);
})->with([
    'super admin staff' => [RoleName::SuperAdmin, '/api/v1/admin/staff', 200],
    'super admin roles' => [RoleName::SuperAdmin, '/api/v1/admin/roles', 200],
    'admin users' => [RoleName::Admin, '/api/v1/admin/users', 200],
    'admin providers' => [RoleName::Admin, '/api/v1/admin/providers', 200],
    'admin staff denied' => [RoleName::Admin, '/api/v1/admin/staff', 403],
    'admin roles denied' => [RoleName::Admin, '/api/v1/admin/roles', 403],
    'admin wallets denied' => [RoleName::Admin, '/api/v1/admin/wallets', 403],
    'support customers' => [RoleName::Support, '/api/v1/admin/users', 200],
    'support tickets' => [RoleName::Support, '/api/v1/admin/support/tickets', 200],
    'support payments denied' => [RoleName::Support, '/api/v1/admin/payments', 403],
    'support providers denied' => [RoleName::Support, '/api/v1/admin/providers', 403],
    'support staff denied' => [RoleName::Support, '/api/v1/admin/staff', 403],
    'operator orders' => [RoleName::Operator, '/api/v1/admin/orders', 200],
    'operator esims' => [RoleName::Operator, '/api/v1/admin/esim/profiles', 200],
    'operator providers' => [RoleName::Operator, '/api/v1/admin/providers', 200],
    'operator payments denied' => [RoleName::Operator, '/api/v1/admin/payments', 403],
    'operator staff denied' => [RoleName::Operator, '/api/v1/admin/staff', 403],
    'finance payments' => [RoleName::Finance, '/api/v1/admin/payments', 200],
    'finance wallets' => [RoleName::Finance, '/api/v1/admin/wallets', 200],
    'finance refunds' => [RoleName::Finance, '/api/v1/admin/refunds', 200],
    'finance providers denied' => [RoleName::Finance, '/api/v1/admin/providers', 403],
    'finance staff denied' => [RoleName::Finance, '/api/v1/admin/staff', 403],
]);

it('requires authentication and permission for staff management', function (): void {
    $this->getJson('/api/v1/admin/staff')->assertUnauthorized();

    Sanctum::actingAs(customer(), ['*']);
    $this->getJson('/api/v1/admin/staff')->assertForbidden();
});

it('lets super admin create, update, suspend, and activate staff', function (): void {
    $actor = staff(RoleName::SuperAdmin);
    Sanctum::actingAs($actor, ['*']);

    $created = $this->postJson('/api/v1/admin/staff', [
        'name' => 'Nour Saleh',
        'email' => 'nour@travora.test',
        'phone' => '+218911112233',
        'role' => RoleName::Support->value,
        'status' => 'active',
    ])->assertCreated()
        ->assertJsonPath('data.email', 'nour@travora.test')
        ->assertJsonPath('data.role', RoleName::Support->value)
        ->assertJsonPath('data.status', 'active');

    $id = $created->json('data.id');

    $this->patchJson('/api/v1/admin/staff/'.$id, [
        'name' => 'Nour S.',
        'role' => RoleName::Operator->value,
    ])->assertOk()->assertJsonPath('data.role', RoleName::Operator->value);

    $this->patchJson('/api/v1/admin/staff/'.$id.'/status', [
        'status' => 'suspended',
        'reason' => 'leave',
    ])->assertOk()->assertJsonPath('data.status', 'suspended');

    $this->patchJson('/api/v1/admin/staff/'.$id.'/status', [
        'status' => 'active',
    ])->assertOk()->assertJsonPath('data.status', 'active');

    expect(AuditLog::query()->where('action', 'staff.created')->where('entity_id', $id)->exists())->toBeTrue()
        ->and(AuditLog::query()->where('action', 'staff.role_changed')->where('entity_id', $id)->exists())->toBeTrue()
        ->and(AuditLog::query()->where('action', 'staff.suspended')->where('entity_id', $id)->exists())->toBeTrue()
        ->and(AuditLog::query()->where('action', 'staff.activated')->where('entity_id', $id)->exists())->toBeTrue();
});

it('denies suspended staff at login and on an existing token', function (): void {
    $user = staff(RoleName::Support);
    $user->password_hash = MockStaffSeeder::PASSWORD;
    $user->status = UserStatus::Suspended;
    $user->save();

    $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => MockStaffSeeder::PASSWORD,
    ])->assertStatus(422);

    $token = $user->createToken('portal', ['admin'])->plainTextToken;
    $user->status = UserStatus::Suspended;
    $user->save();

    $this->withToken($token)->getJson('/api/v1/auth/me')->assertUnauthorized();
});

it('stops an admin from creating or editing a super admin', function (): void {
    $admin = staff(RoleName::Admin);
    $admin->givePermissionTo([
        PermissionName::StaffCreate->value,
        PermissionName::StaffUpdate->value,
        PermissionName::StaffSuspend->value,
        PermissionName::StaffRolesManage->value,
        PermissionName::StaffPermissionsManage->value,
    ]);
    $target = staff(RoleName::SuperAdmin);
    Sanctum::actingAs($admin, ['*']);

    $before = User::query()->count();

    $this->postJson('/api/v1/admin/staff', [
        'name' => 'Escalated',
        'email' => 'escalated@travora.test',
        'role' => RoleName::SuperAdmin->value,
    ])->assertForbidden();

    $this->patchJson('/api/v1/admin/staff/'.$target->id, [
        'name' => 'Changed',
    ])->assertForbidden();

    $this->patchJson('/api/v1/admin/staff/'.$target->id.'/status', [
        'status' => 'suspended',
    ])->assertForbidden();

    $this->putJson('/api/v1/admin/staff/'.$target->id.'/permissions', [
        'permissions' => [PermissionName::RefundsCreate->value],
    ])->assertForbidden();

    expect(User::query()->count())->toBe($before)
        ->and(User::query()->where('email', 'escalated@travora.test')->exists())->toBeFalse()
        ->and($target->refresh()->status)->toBe(UserStatus::Active)
        ->and($target->hasRole(RoleName::SuperAdmin->value))->toBeTrue();
});

it('stops an admin from changing role permissions or granting finance access they do not hold', function (): void {
    $admin = staff(RoleName::Admin);
    $admin->givePermissionTo(PermissionName::RolesPermissionsManage->value);
    $support = Role::findByName(RoleName::Support->value, 'web');
    Sanctum::actingAs($admin, ['*']);

    $this->putJson('/api/v1/admin/roles/'.$support->id.'/permissions', [
        'permissions' => [PermissionName::RefundsCreate->value],
    ])->assertForbidden();

    expect($support->fresh()->hasPermissionTo(PermissionName::RefundsCreate->value))->toBeFalse();
});

it('keeps direct permissions separate from role permissions', function (): void {
    $actor = staff(RoleName::SuperAdmin);
    $member = staff(RoleName::Support);
    Sanctum::actingAs($actor, ['*']);

    $this->putJson('/api/v1/admin/staff/'.$member->id.'/permissions', [
        'permissions' => [PermissionName::RefundsView->value],
    ])->assertOk()
        ->assertJsonPath('data.direct_permissions', [PermissionName::RefundsView->value]);

    $response = $this->getJson('/api/v1/admin/staff/'.$member->id)->assertOk();
    $rolePermissions = $response->json('data.role_permissions');
    $direct = $response->json('data.direct_permissions');
    $effective = $response->json('data.effective_permissions');

    expect($rolePermissions)->toContain(PermissionName::TicketsView->value)
        ->and($rolePermissions)->not->toContain(PermissionName::RefundsView->value)
        ->and($direct)->toBe([PermissionName::RefundsView->value])
        ->and($effective)->toContain(PermissionName::TicketsView->value)
        ->and($effective)->toContain(PermissionName::RefundsView->value)
        ->and(count($effective))->toBe(count(array_unique($effective)))
        ->and($response->json('data.effective_permissions_count'))->toBe(count($rolePermissions) + 1);

    Sanctum::actingAs($member->fresh(), ['*']);
    $this->getJson('/api/v1/admin/refunds')->assertOk();
    $this->getJson('/api/v1/admin/providers')->assertForbidden();

    expect(AuditLog::query()->where('action', 'staff.permission_granted')->where('entity_id', $member->id)->exists())->toBeTrue();
});

it('lets super admin edit a lower role matrix and refuses to strip super admin', function (): void {
    $actor = staff(RoleName::SuperAdmin);
    Sanctum::actingAs($actor, ['*']);
    $support = Role::findByName(RoleName::Support->value, 'web');
    $super = Role::findByName(RoleName::SuperAdmin->value, 'web');
    $superCount = $super->permissions()->count();

    $this->putJson('/api/v1/admin/roles/'.$super->id.'/permissions', [
        'permissions' => [PermissionName::DashboardView->value],
    ])->assertForbidden();

    expect($super->fresh()->permissions()->count())->toBe($superCount);

    $this->putJson('/api/v1/admin/roles/'.$support->id.'/permissions', [
        'permissions' => [
            PermissionName::DashboardView->value,
            PermissionName::TicketsView->value,
        ],
    ])->assertOk();

    auth()->shouldUse('web');
    $member = staff(RoleName::Support);
    Sanctum::actingAs($member, ['*']);
    $this->getJson('/api/v1/admin/users')->assertForbidden();
    $this->getJson('/api/v1/admin/support/tickets')->assertOk();

    expect(AuditLog::query()->where('action', 'role.permission_revoked')->exists())->toBeTrue();
});

it('seeds mock staff only outside production', function (): void {
    app(MockStaffSeeder::class)->run();

    $this->postJson('/api/v1/auth/login', [
        'email' => 'finance@travora',
        'password' => MockStaffSeeder::PASSWORD,
    ])->assertOk()
        ->assertJsonPath('data.user.roles.0', RoleName::Finance->value);

    $this->app['env'] = 'production';
    User::query()->where('email', 'operator@travora')->delete();
    app(MockStaffSeeder::class)->run();

    expect(User::query()->where('email', 'operator@travora')->exists())->toBeFalse();
});

it('returns only the dashboard slices each permission allows', function (RoleName $role, array $present, array $absent): void {
    Sanctum::actingAs(staff($role), ['*']);

    $data = $this->getJson('/api/v1/admin/dashboard')->assertOk()->json('data');

    expect($data)->toHaveKey('range');
    foreach ($present as $key) {
        expect($data)->toHaveKey($key);
    }
    foreach ($absent as $key) {
        expect($data)->not->toHaveKey($key);
    }
})->with([
    'super admin' => [RoleName::SuperAdmin, ['users_total', 'revenue_minor', 'wallet_available_minor', 'esim_sales', 'tickets_open', 'failed_webhooks', 'topups_count'], []],
    'admin' => [RoleName::Admin, ['users_total', 'orders_total', 'esim_sales', 'tickets_open', 'providers_by_health', 'failed_webhooks'], ['revenue_minor', 'wallet_available_minor', 'payment_activity', 'topups_count']],
    'support' => [RoleName::Support, ['users_total', 'orders_total', 'esim_sales', 'tickets_open'], ['revenue_minor', 'wallet_available_minor', 'payment_activity', 'providers_by_health', 'failed_webhooks', 'topups_count', 'failed_orders']],
    'operator' => [RoleName::Operator, ['orders_total', 'esim_sales', 'failed_webhooks', 'providers_by_health', 'pending_fulfillments', 'tickets_open'], ['users_total', 'revenue_minor', 'wallet_available_minor', 'payment_activity']],
    'finance' => [RoleName::Finance, ['orders_total', 'revenue_minor', 'wallet_available_minor', 'topups_count', 'payment_activity'], ['users_total', 'esim_sales', 'tickets_open', 'providers_by_health', 'failed_webhooks']],
]);

it('adds a financial dashboard slice from a direct permission', function (): void {
    $support = staff(RoleName::Support);
    $support->givePermissionTo(PermissionName::PaymentsView->value);
    Sanctum::actingAs($support, ['*']);

    $this->getJson('/api/v1/admin/dashboard')
        ->assertOk()
        ->assertJsonStructure(['data' => ['revenue_minor', 'users_total']])
        ->assertJsonMissingPath('data.wallet_available_minor');
});
