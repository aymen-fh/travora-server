<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\ProviderApiLog;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class CleanupProviderApiLogsJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $uniqueFor = 3600;

    public function uniqueId(): string
    {
        return 'cleanup-provider-api-logs';
    }

    public function handle(): void
    {
        $normalDays = max(1, (int) config('esim.api_log_retention_days', 14));
        $errorDays = max($normalDays, (int) config('esim.api_log_error_retention_days', 90));

        ProviderApiLog::query()
            ->whereNull('error_message')
            ->where('created_at', '<', now()->subDays($normalDays))
            ->delete();

        ProviderApiLog::query()
            ->whereNotNull('error_message')
            ->where('created_at', '<', now()->subDays($errorDays))
            ->delete();
    }
}
