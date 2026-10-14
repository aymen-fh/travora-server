<?php

declare(strict_types=1);

use App\Support\Database\TravoraSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        TravoraSchema::uniqueWhere(
            'wallet_accounts_system_code_currency_unique',
            'wallet_accounts',
            ['code', 'currency'],
            'wallet_id IS NULL',
            'wallet_id IS NULL',
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS wallet_accounts_system_code_currency_unique');
    }
};
