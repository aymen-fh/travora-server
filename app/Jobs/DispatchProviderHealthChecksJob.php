<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\RecordStatus;
use App\Models\Provider;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class DispatchProviderHealthChecksJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $uniqueFor = 240;

    public function uniqueId(): string
    {
        return 'dispatch-provider-health-checks';
    }

    public function handle(): void
    {
        Provider::query()
            ->where('status', RecordStatus::Active)
            ->orderBy('id')
            ->each(function (Provider $provider): void {
                HealthCheckProviderJob::dispatch($provider->id);
            });
    }
}
