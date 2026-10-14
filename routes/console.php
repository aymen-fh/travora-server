<?php

declare(strict_types=1);

use App\Jobs\CleanupAuditLogsJob;
use App\Jobs\CleanupProviderApiLogsJob;
use App\Jobs\DispatchProviderCatalogSyncJob;
use App\Jobs\DispatchProviderHealthChecksJob;
use App\Jobs\ExpireEsimProfilesJob;
use App\Jobs\ExpireWalletHoldsJob;
use App\Jobs\SweepStaleFulfillmentsJob;
use App\Jobs\SyncProviderUsageJob;
use Illuminate\Support\Facades\Schedule;

Schedule::job(new DispatchProviderHealthChecksJob)->everyFiveMinutes();
Schedule::job(new SyncProviderUsageJob)->everyFifteenMinutes();
Schedule::job(new DispatchProviderCatalogSyncJob)->hourly();
Schedule::job(new ExpireWalletHoldsJob)->everyFiveMinutes();
Schedule::job(new SweepStaleFulfillmentsJob)->everyFiveMinutes();
Schedule::job(new ExpireEsimProfilesJob)->daily();
Schedule::job(new CleanupProviderApiLogsJob)->daily();
Schedule::job(new CleanupAuditLogsJob)->daily();
