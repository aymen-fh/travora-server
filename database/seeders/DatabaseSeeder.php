<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Support\Rbac\RolePermissionMatrix;
use Illuminate\Database\Seeder;

final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolePermissionSeeder::class);

        if (! RolePermissionMatrix::allowsMockAccounts()) {
            return;
        }

        $this->call(MockStaffSeeder::class);
    }
}
