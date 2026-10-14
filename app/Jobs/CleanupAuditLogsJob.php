<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\AuditLog;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class CleanupAuditLogsJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $uniqueFor = 3600;

    public function uniqueId(): string
    {
        return 'cleanup-audit-logs';
    }

    public function handle(): void
    {
        $days = max(30, (int) config('travora.audit_log_retention_days', 365));

        AuditLog::query()
            ->where('created_at', '<', now()->subDays($days))
            ->delete();
    }
}
