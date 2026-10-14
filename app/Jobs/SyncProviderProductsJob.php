<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Providers\SyncProviderProducts;
use App\Integrations\Esim\Exceptions\ProviderException;
use App\Models\Provider;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

final class SyncProviderProductsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public readonly string $providerId,
    ) {}

    public function handle(SyncProviderProducts $sync): void
    {
        $provider = Provider::query()->find($this->providerId);
        if ($provider === null) {
            return;
        }

        try {
            $sync($provider);
        } catch (InvalidArgumentException|ProviderException $exception) {
            Log::warning('Provider catalog sync skipped', [
                'provider_id' => $this->providerId,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Product sync failed', [
            'provider_id' => $this->providerId,
            'error' => $exception->getMessage(),
        ]);
    }
}
