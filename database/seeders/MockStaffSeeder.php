<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Enums\WalletStatus;
use App\Models\User;
use App\Models\UserProfile;
use App\Models\Wallet;
use App\Support\Rbac\RolePermissionMatrix;
use Illuminate\Database\Seeder;

/**
 * Development accounts only. Refuses to run in production.
 */
final class MockStaffSeeder extends Seeder
{
    public const PASSWORD = '123456';

    public function run(): void
    {
        if (! RolePermissionMatrix::allowsMockAccounts()) {
            return;
        }

        $accounts = [
            ['email' => 'superadmin@travora', 'legacy_email' => 'superadmin@travora.test', 'name' => 'Super Admin', 'phone' => '+218910000001', 'role' => RoleName::SuperAdmin],
            ['email' => 'admin@travora', 'legacy_email' => 'admin@travora.test', 'name' => 'Platform Admin', 'phone' => '+218910000000', 'role' => RoleName::Admin],
            ['email' => 'support@travora', 'legacy_email' => 'support@travora.test', 'name' => 'Support Agent', 'phone' => '+218910000002', 'role' => RoleName::Support],
            ['email' => 'operator@travora', 'legacy_email' => 'operator@travora.test', 'name' => 'Operations Lead', 'phone' => '+218910000003', 'role' => RoleName::Operator],
            ['email' => 'finance@travora', 'legacy_email' => 'finance@travora.test', 'name' => 'Finance Lead', 'phone' => '+218910000004', 'role' => RoleName::Finance],
        ];

        foreach ($accounts as $account) {
            $user = User::query()->where('email', $account['email'])->first()
                ?? User::query()->where('email', $account['legacy_email'])->first()
                ?? new User;
            $user->email = $account['email'];
            $user->phone = $account['phone'];
            $user->password_hash = self::PASSWORD;
            $user->status = UserStatus::Active;
            $user->email_verified_at ??= now();
            $user->save();

            $profile = $user->profile ?? new UserProfile;
            $profile->user_id = $user->id;
            $profile->display_name = $account['name'];
            $profile->first_name ??= $account['name'];
            $profile->preferred_language ??= 'en';
            $profile->save();

            $user->syncRoles([$account['role']->value]);

            if (! Wallet::query()->where('user_id', $user->id)->where('currency', 'LYD')->exists()) {
                $wallet = new Wallet;
                $wallet->user_id = $user->id;
                $wallet->currency = 'LYD';
                $wallet->available_balance_minor = 0;
                $wallet->held_balance_minor = 0;
                $wallet->status = WalletStatus::Active;
                $wallet->save();
            }
        }
    }
}
