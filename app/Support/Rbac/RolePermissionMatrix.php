<?php

declare(strict_types=1);

namespace App\Support\Rbac;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\User;

/**
 * Domain permissions are the staff catalog. Legacy permission names stay
 * attached as aliases so existing routes and policies keep authorizing.
 */
final class RolePermissionMatrix
{
    /**
     * @return list<string>
     */
    public static function staffRoleNames(): array
    {
        return [
            RoleName::SuperAdmin->value,
            RoleName::Admin->value,
            RoleName::Support->value,
            RoleName::Operator->value,
            RoleName::Finance->value,
        ];
    }

    /**
     * @return list<string>
     */
    public static function assignableStaffRoles(): array
    {
        return self::staffRoleNames();
    }

    /**
     * @return list<string>
     */
    public static function builtInRoles(): array
    {
        return array_map(fn (RoleName $role): string => $role->value, RoleName::cases());
    }

    public static function allowsMockAccounts(): bool
    {
        return app()->environment(['local', 'development', 'testing', 'staging']);
    }

    /**
     * @return list<string>
     */
    public static function forRole(RoleName $role): array
    {
        if ($role === RoleName::SuperAdmin) {
            return array_map(fn (PermissionName $permission): string => $permission->value, PermissionName::cases());
        }

        if ($role === RoleName::User) {
            return [];
        }

        return self::expandForRole($role->value, self::domainGrants($role));
    }

    /**
     * @param  list<string>  $permissions
     * @return list<string>
     */
    public static function expandForRole(string $roleName, array $permissions): array
    {
        $expanded = self::expand($permissions);

        if ($roleName === RoleName::Operator->value) {
            $expanded = array_values(array_diff($expanded, [
                PermissionName::ProvidersServicesManage->value,
                PermissionName::ProvidersCoverageManage->value,
            ]));
        }

        return $expanded;
    }

    /**
     * Permissions shown in staff and role screens. Legacy aliases are omitted.
     *
     * @return list<string>
     */
    public static function catalogNames(): array
    {
        $names = [];
        foreach (self::catalog() as $group) {
            foreach ($group['permissions'] as $permission) {
                $names[] = $permission['name'];
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * @param  list<string>  $permissions
     * @return list<string>
     */
    public static function expand(array $permissions): array
    {
        $expanded = [];
        foreach ($permissions as $permission) {
            $expanded[] = $permission;
            foreach (self::aliases($permission) as $alias) {
                $expanded[] = $alias;
            }
        }

        return array_values(array_unique($expanded));
    }

    /**
     * @param  list<string>  $stored
     * @return list<string>
     */
    public static function visiblePermissions(array $stored): array
    {
        return array_values(array_intersect(self::catalogNames(), $stored));
    }

    /**
     * @return array{role_permissions: list<string>, direct_permissions: list<string>, effective_permissions: list<string>}
     */
    public static function breakdown(User $user): array
    {
        $role = self::visiblePermissions($user->getPermissionsViaRoles()->pluck('name')->all());
        $direct = self::visiblePermissions($user->getDirectPermissions()->pluck('name')->all());
        $effective = array_values(array_unique([...$role, ...$direct]));
        sort($role);
        sort($direct);
        sort($effective);

        return [
            'role_permissions' => $role,
            'direct_permissions' => $direct,
            'effective_permissions' => $effective,
        ];
    }

    public static function description(RoleName|string $role): string
    {
        $name = $role instanceof RoleName ? $role->value : $role;

        return match ($name) {
            RoleName::SuperAdmin->value => 'System owner. Full platform access, staff, roles, and system settings.',
            RoleName::Admin->value => 'Platform administrator for daily operations. Cannot manage Super Admin or system ownership.',
            RoleName::Support->value => 'Customer support. Customers, orders, eSIM lookup, and tickets.',
            RoleName::Operator->value => 'Connectivity operations. Orders, eSIM, inventory, providers, and webhooks.',
            RoleName::Finance->value => 'Finance operations. Payments, wallets, top-ups, refunds, and revenue.',
            RoleName::User->value => 'Customer account. No staff access.',
            default => '',
        };
    }

    public static function label(string $role): string
    {
        return match ($role) {
            RoleName::SuperAdmin->value => 'Super Admin',
            RoleName::Admin->value => 'Admin',
            RoleName::Support->value => 'Support',
            RoleName::Operator->value => 'Operator',
            RoleName::Finance->value => 'Finance',
            RoleName::User->value => 'Customer',
            default => str_replace('_', ' ', $role),
        };
    }

    /**
     * @return list<array{key: string, permissions: list<array{name: string, action: string}>}>
     */
    public static function catalog(): array
    {
        return [
            self::group('dashboard', [
                ['dashboard.view', 'view'],
            ]),
            self::group('users', [
                ['users.view', 'view'],
                ['users.view_detail', 'view_detail'],
                ['users.create', 'create'],
                ['users.update', 'update'],
                ['users.suspend', 'suspend'],
            ]),
            self::group('orders', [
                ['orders.view', 'view'],
                ['orders.view_detail', 'view_detail'],
                ['orders.update', 'update'],
                ['orders.manage', 'manage'],
            ]),
            self::group('esims', [
                ['esims.view', 'view'],
                ['esims.view_detail', 'view_detail'],
                ['esims.manage', 'manage'],
            ]),
            self::group('esim_products', [
                ['esim_products.view', 'view'],
                ['esim_products.create', 'create'],
                ['esim_products.update', 'update'],
                ['esim_products.delete', 'delete'],
            ]),
            self::group('sim_inventory', [
                ['sim_inventory.view', 'view'],
                ['sim_inventory.manage', 'manage'],
            ]),
            self::group('providers', [
                ['providers.view', 'view'],
                ['providers.manage', 'manage'],
            ]),
            self::group('network_operators', [
                ['network_operators.view', 'view'],
                ['network_operators.manage', 'manage'],
            ]),
            self::group('payments', [
                ['payments.view', 'view'],
                ['payments.view_detail', 'view_detail'],
                ['payments.manage', 'manage'],
            ]),
            self::group('wallets', [
                ['wallets.view', 'view'],
                ['wallets.view_detail', 'view_detail'],
                ['wallets.manage', 'manage'],
            ]),
            self::group('topups', [
                ['topups.view', 'view'],
                ['topups.view_detail', 'view_detail'],
                ['topups.manage', 'manage'],
            ]),
            self::group('refunds', [
                ['refunds.view', 'view'],
                ['refunds.create', 'create'],
                ['refunds.manage', 'manage'],
            ]),
            self::group('finance', [
                ['finance.dashboard.view', 'view'],
                ['finance.revenue.view', 'view'],
                ['finance.reports.view', 'view'],
            ]),
            self::group('tickets', [
                ['tickets.view', 'view'],
                ['tickets.view_detail', 'view_detail'],
                ['tickets.create', 'create'],
                ['tickets.update', 'update'],
                ['tickets.assign', 'assign'],
                ['tickets.close', 'close'],
            ]),
            self::group('notifications', [
                ['notifications.view', 'view'],
                ['notifications.create', 'create'],
                ['notifications.manage', 'manage'],
            ]),
            self::group('promotions', [
                ['promotions.view', 'view'],
                ['promotions.create', 'create'],
                ['promotions.update', 'update'],
                ['promotions.delete', 'delete'],
            ]),
            self::group('webhooks', [
                ['webhooks.view', 'view'],
                ['webhooks.retry', 'retry'],
            ]),
            self::group('audit', [
                ['audit.view', 'view'],
            ]),
            self::group('staff', [
                ['staff.view', 'view'],
                ['staff.view_detail', 'view_detail'],
                ['staff.create', 'create'],
                ['staff.update', 'update'],
                ['staff.suspend', 'suspend'],
                ['staff.activate', 'activate'],
                ['staff.roles.manage', 'manage'],
                ['staff.permissions.manage', 'manage'],
            ]),
            self::group('roles', [
                ['roles.view', 'view'],
                ['roles.create', 'create'],
                ['roles.update', 'update'],
                ['roles.delete', 'delete'],
                ['roles.permissions.manage', 'manage'],
            ]),
            self::group('system', [
                ['system.settings.view', 'view'],
                ['system.settings.manage', 'manage'],
            ]),
        ];
    }

    /**
     * @return list<string>
     */
    private static function domainGrants(RoleName $role): array
    {
        $retained = match ($role) {
            RoleName::Admin => [
                PermissionName::UsersEdit->value,
                PermissionName::UsersSessionsView->value,
                PermissionName::UsersSessionsRevoke->value,
                PermissionName::ProvidersCredentialsViewMasked->value,
            ],
            RoleName::Finance => [
                PermissionName::WalletsAdjust->value,
            ],
            default => [],
        };

        return array_values(array_unique([...self::domain($role), ...$retained]));
    }

    /**
     * @return list<string>
     */
    private static function domain(RoleName $role): array
    {
        return match ($role) {
            RoleName::Admin => [
                'dashboard.view',
                'users.view', 'users.view_detail', 'users.create', 'users.update', 'users.suspend',
                'orders.view', 'orders.view_detail', 'orders.update', 'orders.manage',
                'esims.view', 'esims.view_detail', 'esims.manage',
                'esim_products.view', 'esim_products.create', 'esim_products.update', 'esim_products.delete',
                'sim_inventory.view', 'sim_inventory.manage',
                'providers.view', 'providers.manage',
                'network_operators.view', 'network_operators.manage',
                'tickets.view', 'tickets.view_detail', 'tickets.create', 'tickets.update', 'tickets.assign', 'tickets.close',
                'notifications.view', 'notifications.create', 'notifications.manage',
                'promotions.view', 'promotions.create', 'promotions.update', 'promotions.delete',
                'webhooks.view', 'webhooks.retry',
                'audit.view',
            ],
            RoleName::Support => [
                'dashboard.view',
                'users.view', 'users.view_detail',
                'orders.view', 'orders.view_detail',
                'esims.view', 'esims.view_detail',
                'tickets.view', 'tickets.view_detail', 'tickets.create', 'tickets.update', 'tickets.assign', 'tickets.close',
                'notifications.view',
            ],
            RoleName::Operator => [
                'dashboard.view',
                'orders.view', 'orders.view_detail', 'orders.manage',
                'esims.view', 'esims.view_detail', 'esims.manage',
                'sim_inventory.view', 'sim_inventory.manage',
                'esim_products.view',
                'providers.view', 'providers.manage',
                'network_operators.view', 'network_operators.manage',
                'webhooks.view', 'webhooks.retry',
                'tickets.view',
            ],
            RoleName::Finance => [
                'dashboard.view',
                'payments.view', 'payments.view_detail',
                'wallets.view', 'wallets.view_detail',
                'topups.view', 'topups.view_detail',
                'refunds.view', 'refunds.create', 'refunds.manage',
                'finance.dashboard.view', 'finance.revenue.view', 'finance.reports.view',
                'orders.view', 'orders.view_detail',
                'audit.view',
            ],
            default => [],
        };
    }

    /**
     * @return list<string>
     */
    private static function aliases(string $permission): array
    {
        return match ($permission) {
            'esims.view' => ['esim.profiles.view'],
            'esims.view_detail' => ['esim.usage.view'],
            'esims.manage' => ['esim.usage.sync'],
            'esim_products.view' => ['esim.plans.view'],
            'esim_products.create', 'esim_products.update' => ['esim.plans.manage', 'esim.prices.view', 'esim.prices.manage', 'products.manage'],
            'esim_products.delete' => ['products.manage'],
            'sim_inventory.view' => [],
            'sim_inventory.manage' => ['products.manage'],
            'orders.manage' => ['orders.cancel', 'orders.retry_fulfillment'],
            'providers.manage' => [
                'providers.enable',
                'providers.disable',
                'providers.products.sync',
                'providers.health.run',
                'providers.services.manage',
                'providers.coverage.manage',
            ],
            'network_operators.view' => ['esim.plans.view'],
            'network_operators.manage' => ['esim.plans.manage', 'esim.prices.view', 'esim.prices.manage'],
            'wallets.view_detail' => ['wallets.transactions.view', 'wallets.holds.view', 'wallets.ledger.view', 'wallet.view'],
            'wallets.manage' => ['wallets.adjust', 'wallet.adjust'],
            'refunds.view' => ['payment_refunds.view'],
            'refunds.create', 'refunds.manage' => ['payments.refund', 'orders.refund'],
            'tickets.view', 'tickets.view_detail' => ['support.tickets.view'],
            'tickets.assign' => ['support.tickets.assign'],
            'tickets.update' => ['support.tickets.reply'],
            'tickets.close' => ['support.tickets.close'],
            'tickets.create' => ['support.manage'],
            'promotions.view' => ['products.view'],
            'promotions.create', 'promotions.update', 'promotions.delete' => ['products.manage'],
            'webhooks.view' => ['webhooks.dead_letter.view'],
            'finance.reports.view' => ['reports.view'],
            'audit.view' => ['logs.view'],
            'system.settings.manage' => ['settings.manage'],
            default => [],
        };
    }

    /**
     * @param  list<array{0: string, 1: string}>  $permissions
     * @return array{key: string, permissions: list<array{name: string, action: string}>}
     */
    private static function group(string $key, array $permissions): array
    {
        return [
            'key' => $key,
            'permissions' => array_map(
                fn (array $permission): array => ['name' => $permission[0], 'action' => $permission[1]],
                $permissions,
            ),
        ];
    }
}
