<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

final class ValidateEnvCommand extends Command
{
    protected $signature = 'travora:validate-env {--strict : Apply production rules regardless of APP_ENV}';

    protected $description = 'Validate required Travora environment configuration before deploy.';

    public function handle(): int
    {
        $errors = [];

        foreach ((array) config('travora.required_env', []) as $key) {
            $value = (string) env($key, '');
            if ($value === '' || $value === 'base64:') {
                $errors[] = $key.' is missing.';
            }
        }

        $strict = $this->option('strict') || app()->environment('production');

        if ($strict) {
            if (filter_var(env('APP_DEBUG'), FILTER_VALIDATE_BOOL)) {
                $errors[] = 'APP_DEBUG must be false in production.';
            }

            if ((string) env('QUEUE_CONNECTION') === 'sync') {
                $errors[] = 'QUEUE_CONNECTION must not be sync in production.';
            }

            if ((string) env('DB_CONNECTION') !== 'pgsql') {
                $errors[] = 'DB_CONNECTION must be pgsql in production.';
            }

            if (in_array((string) env('CACHE_STORE'), ['array', ''], true)) {
                $errors[] = 'CACHE_STORE must be a durable store in production.';
            }

            if ((string) env('DB_PASSWORD') === 'secret') {
                $errors[] = 'DB_PASSWORD must not use the example value.';
            }

            if ((string) env('PROVIDER_LOG_LEVEL', 'basic') === 'full') {
                $errors[] = 'PROVIDER_LOG_LEVEL=full is not allowed in production.';
            }
        }

        if ($errors !== []) {
            foreach ($errors as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $this->info('Environment configuration is valid.');

        return self::SUCCESS;
    }
}
