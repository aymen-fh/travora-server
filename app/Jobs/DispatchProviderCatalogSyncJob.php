<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\RecordStatus;
use App\Models\Provider;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class DispatchProviderCatalogSyncJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $uniqueFor = 3300;

    public function uniqueId(): string
    {
        return 'dispatch-provider-catalog-sync';
    }

    public function handle(): void
    {
        Provider::query()
            ->where('status', RecordStatus::Active)
            ->orderBy('id')
            ->each(function (Provider $provider): void {
                SyncProviderProductsJob::dispatch($provider->id);
            });
    }
}
