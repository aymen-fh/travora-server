<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class HealthCommand extends Command
{
    protected $signature = 'travora:health';

    protected $description = 'Check application, database, cache, and queue readiness without printing secrets.';

    public function handle(): int
    {
        $failed = false;

        $checks = [
            'app' => fn (): string => (string) app()->environment(),
            'database' => function (): string {
                DB::select('select 1');

                return (string) config('database.default');
            },
            'cache' => function (): string {
                $key = 'travora-health-'.bin2hex(random_bytes(4));
                Cache::put($key, 'ok', 5);

                if (Cache::pull($key) !== 'ok') {
                    throw new \RuntimeException('Cache round-trip failed.');
                }

                return (string) config('cache.default');
            },
            'queue' => fn (): string => (string) config('queue.default'),
            'failed_jobs' => function (): string {
                if (! Schema::hasTable('failed_jobs')) {
                    throw new \RuntimeException('failed_jobs table is missing.');
                }

                return (string) DB::table('failed_jobs')->count();
            },
        ];

        foreach ($checks as $name => $check) {
            try {
                $this->line(sprintf('ok  %s  %s', $name, $check()));
            } catch (Throwable $exception) {
                $failed = true;
                $this->error(sprintf('fail  %s  %s', $name, $exception->getMessage()));
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
