<?php

declare(strict_types=1);

namespace App\Support\Database;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class TravoraSchema
{
    public static function uuidPrimary(Blueprint $table): void
    {
        $table->uuid('id')->primary();
    }

    public static function timestampsTz(Blueprint $table, bool $withUpdatedAt = true): void
    {
        $table->timestampTz('created_at');

        if ($withUpdatedAt) {
            $table->timestampTz('updated_at');
        }
    }

    public static function isPgsql(): bool
    {
        return Schema::getConnection()->getDriverName() === 'pgsql';
    }

    public static function uniqueWhere(string $index, string $table, array $columns, string $postgresWhere, string $sqliteWhere): void
    {
        $quoted = implode(', ', array_map(
            static fn (string $column): string => '"'.$column.'"',
            $columns,
        ));

        $where = self::isPgsql() ? $postgresWhere : $sqliteWhere;

        DB::statement(sprintf(
            'CREATE UNIQUE INDEX %s ON %s (%s) WHERE %s',
            $index,
            $table,
            $quoted,
            $where,
        ));
    }

    /**
     * @param  array<string, string>  $checks
     */
    public static function addChecks(string $table, array $checks): void
    {
        if (! self::isPgsql()) {
            return;
        }

        foreach ($checks as $name => $expression) {
            DB::statement(sprintf(
                'ALTER TABLE %s ADD CONSTRAINT %s CHECK (%s)',
                $table,
                $name,
                $expression,
            ));
        }
    }
}
